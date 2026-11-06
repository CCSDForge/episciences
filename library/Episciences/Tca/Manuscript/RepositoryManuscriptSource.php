<?php

declare(strict_types=1);

namespace Episciences\Tca\Manuscript;

use Closure;
use Episciences\Log\LoggerFactory;
use Episciences\Tca\Contract\ManuscriptFile;
use Episciences\Tca\Contract\ManuscriptSourceInterface;
use Episciences\Tca\Contract\ManuscriptTemporarilyUnavailableException;
use Episciences\Tca\Contract\ManuscriptTooLargeException;
use Episciences_Paper;
use Episciences_Paper_DocumentBackup;
use Episciences_Paper_File;
use Episciences_Paper_FilesManager;
use Episciences_PapersManager;
use Episciences_ReviewsManager;
use finfo;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Header;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

/**
 * Downloads the manuscript of a paper version from its repository (arXiv, HAL, Zenodo...).
 *
 * The local backup copy of the PDF is used first when it exists: repository files are immutable.
 * Remote files are streamed to a temporary file (never fully loaded in memory), which gives
 * the exact size required by the Turnitin upload. Redirects are followed by hand so that
 * the body of an intermediate response never ends up in the downloaded file, and so that
 * the scheme of every hop is checked.
 */
final readonly class RepositoryManuscriptSource implements ManuscriptSourceInterface
{
    public const int CONNECT_TIMEOUT = 10;
    public const int TIMEOUT = 120;
    public const int MAX_REDIRECTS = 5;

    private const int MIME_SNIFF_BYTES = 8192;
    private const int FILE_NAME_MAX_LENGTH = 200;
    private const string DEFAULT_MIME_TYPE = 'application/octet-stream';
    private const string DEFAULT_EXTENSION = 'bin';

    /** Statuses meaning the file is gone or not accessible without credentials: final, no retry. */
    private const array UNAVAILABLE_STATUSES = [401, 403, 404, 410];

    /** MIME types too vague to describe the file: the file name extension is trusted instead. */
    private const array GENERIC_MIME_TYPES = [
        'application/octet-stream',
        'application/zip',
        'application/x-zip-compressed',
        'application/x-ole-storage',
        'application/cdfv2',
    ];

    /** A web page instead of a file: login, embargo or landing page answered with a 200. */
    private const array WEB_PAGE_MIME_TYPES = ['text/html', 'application/xhtml+xml'];

    /** @var Closure(int): ?Episciences_Paper */
    private Closure $paperLoader;

    /** @var Closure(Episciences_Paper): ?Episciences_Paper_File */
    private Closure $mainFileLoader;

    /** @var Closure(Episciences_Paper): ?string */
    private Closure $backupPathResolver;

    /** @var Closure(string): list<string> */
    private Closure $hostResolver;

    /**
     * @param Closure(int): ?Episciences_Paper|null $paperLoader
     * @param Closure(Episciences_Paper): ?Episciences_Paper_File|null $mainFileLoader
     * @param Closure(Episciences_Paper): ?string|null $backupPathResolver path of a readable local copy, or null
     * @param Closure(string): list<string>|null $hostResolver IPv4 addresses of a host name, empty when it does not resolve
     */
    public function __construct(
        private ClientInterface $httpClient,
        private LoggerInterface $logger,
        ?Closure $paperLoader = null,
        ?Closure $mainFileLoader = null,
        ?Closure $backupPathResolver = null,
        private int $maxBytes = ManuscriptFile::MAX_SIZE,
        private string $userAgent = '',
        ?Closure $hostResolver = null
    ) {
        $this->paperLoader = $paperLoader ?? $this->defaultPaperLoader(...);
        $this->mainFileLoader = $mainFileLoader ?? $this->defaultMainFileLoader(...);
        $this->backupPathResolver = $backupPathResolver ?? $this->defaultBackupPathResolver(...);
        $this->hostResolver = $hostResolver ?? $this->defaultHostResolver(...);
    }

    /**
     * Production wiring.
     */
    public static function create(?LoggerInterface $logger = null): self
    {
        return new self(
            new Client(),
            $logger ?? LoggerFactory::rotating('tca_manuscript'),
            userAgent: defined('EPISCIENCES_USER_AGENT') ? (string) EPISCIENCES_USER_AGENT : ''
        );
    }

    #[\Override]
    public function fetch(int $docId): ?ManuscriptFile
    {
        $paper = ($this->paperLoader)($docId);

        if ($paper === null) {
            $this->logUnavailable($docId, 'paper not found');
            return null;
        }

        if ($paper->isTmp()) {
            $this->logUnavailable($docId, 'temporary version');
            return null;
        }

        if ($paper->isDataSetOrSoftware()) {
            $this->logUnavailable($docId, 'dataset or software');
            return null;
        }

        $mainFile = $paper->hasFilesEnrichment() ? ($this->mainFileLoader)($paper) : null;

        return $this->fromBackup($paper, $mainFile) ?? $this->fromRepository($paper, $mainFile);
    }

    private function fromBackup(Episciences_Paper $paper, ?Episciences_Paper_File $mainFile): ?ManuscriptFile
    {
        $path = ($this->backupPathResolver)($paper);

        if ($path === null) {
            return null;
        }

        $size = (int) filesize($path);

        if ($size <= 0) {
            return null;
        }

        if ($size > $this->maxBytes) {
            throw new ManuscriptTooLargeException($size, $this->maxBytes);
        }

        $stream = Utils::streamFor(Utils::tryFopen($path, 'rb'));
        $manuscript = $this->buildManuscript($paper, $mainFile, $stream, $size, null);

        $this->logger->info('Manuscript read from the local backup', [
            'docid' => $paper->getDocid(),
            'size' => $size,
            'mime' => $manuscript?->mimeType,
        ]);

        return $manuscript;
    }

    private function fromRepository(Episciences_Paper $paper, ?Episciences_Paper_File $mainFile): ?ManuscriptFile
    {
        $docId = (int) $paper->getDocid();

        if ($paper->hasFilesEnrichment() && !$mainFile instanceof Episciences_Paper_File) {
            $this->logUnavailable($docId, 'no file in the repository record');
            return null;
        }

        $url = $mainFile instanceof Episciences_Paper_File ? $mainFile->getSelfLink() : (string) $paper->getPaperUrl();

        if (!$this->isHttpUrl($url)) {
            $this->logUnavailable($docId, 'no downloadable URL', ['url' => $url]);
            return null;
        }

        $start = microtime(true);

        try {
            [$response, $body, $finalUrl] = $this->download($docId, $url);
        } catch (UnavailableManuscript $e) {
            $this->logUnavailable($docId, $e->getMessage(), ['repoid' => $paper->getRepoid(), 'url' => $url]);
            return null;
        }

        $context = [
            'docid' => $docId,
            'repoid' => $paper->getRepoid(),
            'url' => $finalUrl,
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ];

        $size = (int) $body->getSize();

        if ($size <= 0) {
            $this->logUnavailable($docId, 'empty file', $context);
            return null;
        }

        $manuscript = $this->buildManuscript($paper, $mainFile, $body, $size, $response);

        if (!$manuscript instanceof ManuscriptFile) {
            $this->logUnavailable($docId, 'the repository returned a web page instead of a file', $context);
            return null;
        }

        $this->logger->info('Manuscript downloaded from the repository', $context + [
            'size' => $size,
            'mime' => $manuscript->mimeType,
            'file_name' => $manuscript->fileName,
        ]);

        return $manuscript;
    }

    /**
     * Follows the redirects by hand: each hop gets its own sink, and its scheme and host are checked.
     *
     * @return array{0: ResponseInterface, 1: StreamInterface, 2: string} the final response, its body and URL
     * @throws UnavailableManuscript
     * @throws ManuscriptTemporarilyUnavailableException
     * @throws ManuscriptTooLargeException
     */
    private function download(int $docId, string $url): array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $sink = new SizeLimitedStream(Utils::streamFor(Utils::tryFopen('php://temp/maxmemory:0', 'w+b')), $this->maxBytes);
            $response = $this->send($docId, $url, $sink, $this->pinPublicAddress($docId, $url));
            $status = $response->getStatusCode();
            $location = $response->getHeaderLine('Location');

            if ($status >= 300 && $status < 400 && $location !== '') {
                $sink->close();
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
                $this->assertRedirectTarget($docId, $url);
                continue;
            }

            $this->assertSuccessful($docId, $url, $status, $sink);
            $sink->rewind();

            return [$response, $sink, $url];
        }

        throw new ManuscriptTemporarilyUnavailableException(sprintf('Too many redirects while downloading the manuscript of docid %d.', $docId));
    }

    private function send(int $docId, string $url, SizeLimitedStream $sink, string $pinnedAddress): ResponseInterface
    {
        try {
            return $this->httpClient->request('GET', $url, [
                'sink' => $sink,
                'http_errors' => false,
                'allow_redirects' => false,
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout' => self::TIMEOUT,
                'headers' => array_filter(['User-Agent' => $this->userAgent, 'Accept' => '*/*']),
                'on_headers' => $this->rejectAnnouncedOversize(...),
                // Connect to the address that was checked, never to a new DNS answer (DNS rebinding)
                'force_ip_resolve' => 'v4',
                'curl' => [CURLOPT_RESOLVE => [$pinnedAddress]],
            ]);
        } catch (GuzzleException $e) {
            throw $this->translateTransferFailure($docId, $url, $sink, $e);
        }
    }

    /**
     * Stops the download as soon as the announced size of a successful response is too large.
     */
    private function rejectAnnouncedOversize(ResponseInterface $response): void
    {
        $length = $response->getHeaderLine('Content-Length');
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300 && ctype_digit($length) && (int) $length > $this->maxBytes) {
            throw new ManuscriptTooLargeException((int) $length, $this->maxBytes);
        }
    }

    private function translateTransferFailure(int $docId, string $url, SizeLimitedStream $sink, Throwable $e): Throwable
    {
        for ($cause = $e; $cause instanceof Throwable; $cause = $cause->getPrevious()) {
            if ($cause instanceof ManuscriptTooLargeException) {
                return $cause;
            }
        }

        if ($sink->hasExceededLimit()) {
            return new ManuscriptTooLargeException($sink->getBytesReceived(), $this->maxBytes);
        }

        $this->logger->warning('Manuscript download failed', ['docid' => $docId, 'url' => $url, 'error' => $e->getMessage()]);

        return new ManuscriptTemporarilyUnavailableException(
            sprintf('The repository of docid %d could not be reached.', $docId),
            0,
            $e
        );
    }

    private function assertRedirectTarget(int $docId, string $url): void
    {
        if (!$this->isHttpUrl($url)) {
            throw new UnavailableManuscript(sprintf('redirect to a non HTTP URL (%s)', $url));
        }

        $this->logger->debug('Following a repository redirect', ['docid' => $docId, 'url' => $url]);
    }

    private function assertSuccessful(int $docId, string $url, int $status, SizeLimitedStream $sink): void
    {
        if ($status >= 200 && $status < 300) {
            return;
        }

        $sink->close();

        if (in_array($status, self::UNAVAILABLE_STATUSES, true)) {
            throw new UnavailableManuscript(sprintf('HTTP %d from %s', $status, $url));
        }

        $this->logger->warning('Repository error while downloading the manuscript', ['docid' => $docId, 'url' => $url, 'status' => $status]);

        if ($status === 429 || $status >= 500) {
            throw new ManuscriptTemporarilyUnavailableException(sprintf('The repository of docid %d answered HTTP %d.', $docId, $status));
        }

        throw new UnavailableManuscript(sprintf('unexpected HTTP %d from %s', $status, $url));
    }

    /**
     * @return ManuscriptFile|null null when the content is a web page
     */
    private function buildManuscript(
        Episciences_Paper $paper,
        ?Episciences_Paper_File $mainFile,
        StreamInterface $stream,
        int $size,
        ?ResponseInterface $response
    ): ?ManuscriptFile {
        $fileName = $this->sanitizeFileName(
            $this->contentDispositionFileName($response)
            ?? ($mainFile?->getFileName() ?: null)
            ?? sprintf('%sv%s', $paper->getIdentifier(), $paper->getVersion())
        );

        $mimeType = $this->detectMimeType($stream, $response, $fileName);

        if (in_array($mimeType, self::WEB_PAGE_MIME_TYPES, true)) {
            $stream->close();
            return null;
        }

        $fileName = $this->ensureExtension($fileName, $mimeType, $mainFile?->getFileType());

        return new ManuscriptFile($stream, $fileName, $size, $mimeType);
    }

    private function detectMimeType(StreamInterface $stream, ?ResponseInterface $response, string $fileName): string
    {
        $head = $stream->read(self::MIME_SNIFF_BYTES);
        $stream->rewind();

        $detected = strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->buffer($head));

        if ($detected !== '' && !in_array($detected, self::GENERIC_MIME_TYPES, true)) {
            return $detected;
        }

        $fromExtension = MimeTypes::getDefault()->getMimeTypes(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)))[0] ?? null;

        if ($fromExtension !== null) {
            return $fromExtension;
        }

        $announced = strtolower(trim(explode(';', $response?->getHeaderLine('Content-Type') ?? '')[0]));

        if ($announced !== '' && !in_array($announced, self::GENERIC_MIME_TYPES, true)) {
            return $announced;
        }

        return $detected !== '' ? $detected : self::DEFAULT_MIME_TYPE;
    }

    /**
     * Appends the extension matching the MIME type when the name lacks it.
     * Old arXiv identifiers ("2401.01234v2") look like they have an extension: they get one appended too.
     */
    private function ensureExtension(string $fileName, string $mimeType, ?string $fileTypeHint): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $expected = in_array($mimeType, self::GENERIC_MIME_TYPES, true) ? [] : MimeTypes::getDefault()->getExtensions($mimeType);

        if ($expected === []) {
            if ($extension !== '' && preg_match('/^[a-z0-9]{1,8}$/', $extension)) {
                return $fileName;
            }

            $hint = strtolower((string) $fileTypeHint);

            return $fileName . '.' . (preg_match('/^[a-z0-9]{1,8}$/', $hint) ? $hint : self::DEFAULT_EXTENSION);
        }

        return in_array($extension, $expected, true) ? $fileName : $fileName . '.' . $expected[0];
    }

    private function contentDispositionFileName(?ResponseInterface $response): ?string
    {
        $header = $response?->getHeaderLine('Content-Disposition') ?? '';

        if ($header === '') {
            return null;
        }

        $parameters = array_change_key_case(Header::parse($header)[0] ?? []);

        if (isset($parameters['filename*']) && preg_match("/^[\w-]+'[^']*'(.+)$/", $parameters['filename*'], $matches)) {
            return rawurldecode($matches[1]);
        }

        $name = $parameters['filename'] ?? '';

        return $name !== '' ? $name : null;
    }

    /**
     * Keeps the accents (Turnitin receives the name URL-encoded) but drops path separators and control characters.
     */
    private function sanitizeFileName(string $fileName): string
    {
        $fileName = basename(str_replace('\\', '/', $fileName));
        $fileName = (string) preg_replace('/[\x00-\x1F\x7F\/:]+/u', '_', $fileName);
        $fileName = trim($fileName, " .\t");

        if ($fileName === '' || !mb_check_encoding($fileName, 'UTF-8')) {
            return 'manuscript';
        }

        return mb_substr($fileName, -self::FILE_NAME_MAX_LENGTH);
    }

    /**
     * SSRF guard: file links and redirect targets come from third parties (repository APIs, remote servers).
     * Refuses a host that resolves to a loopback, private, link-local or reserved address,
     * and returns the CURLOPT_RESOLVE entry pinning the request to the checked address.
     *
     * @throws UnavailableManuscript
     * @throws ManuscriptTemporarilyUnavailableException
     */
    private function pinPublicAddress(int $docId, string $url): string
    {
        $uri = new Uri($url);
        $host = $uri->getHost();
        $port = $uri->getPort() ?? ($uri->getScheme() === 'http' ? 80 : 443);

        $literal = trim($host, '[]');
        $addresses = filter_var($literal, FILTER_VALIDATE_IP) !== false ? [$literal] : ($this->hostResolver)($host);

        if ($addresses === []) {
            throw new ManuscriptTemporarilyUnavailableException(sprintf('The repository host of docid %d does not resolve.', $docId));
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_GLOBAL_RANGE) === false) {
                $this->logger->warning('Refused a repository URL resolving to a non public address', ['docid' => $docId, 'url' => $url, 'address' => $address]);
                throw new UnavailableManuscript(sprintf('%s resolves to a non public address', $host));
            }
        }

        return sprintf('%s:%d:%s', $host, $port, $addresses[0]);
    }

    private function isHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logUnavailable(int $docId, string $reason, array $context = []): void
    {
        $this->logger->notice('No analysable manuscript', ['docid' => $docId, 'reason' => $reason] + $context);
    }

    private function defaultPaperLoader(int $docId): ?Episciences_Paper
    {
        $paper = Episciences_PapersManager::get($docId, false);

        return $paper instanceof Episciences_Paper ? $paper : null;
    }

    private function defaultMainFileLoader(Episciences_Paper $paper): ?Episciences_Paper_File
    {
        return Episciences_Paper_FilesManager::getMainFile((int) $paper->getDocid());
    }

    /**
     * @return list<string>
     */
    private function defaultHostResolver(string $host): array
    {
        $addresses = gethostbynamel($host);

        return $addresses === false ? [] : $addresses;
    }

    private function defaultBackupPathResolver(Episciences_Paper $paper): ?string
    {
        $journal = Episciences_ReviewsManager::findByRvid($paper->getRvid());

        if (!$journal) {
            return null;
        }

        $backup = new Episciences_Paper_DocumentBackup((int) $paper->getDocid(), (string) $journal->getCode());

        return $backup->hasDocumentBackupFile() ? $backup->getPathFileName() : null;
    }
}

<?php

declare(strict_types=1);

namespace Episciences\Tca\Manuscript;

use Closure;
use Episciences\Files\FileManager;
use Episciences\Http\PublicAddressGuard;
use Episciences\Http\RefusedAddressException;
use Episciences\Http\UnresolvedHostException;
use Episciences\Log\LoggerFactory;
use Episciences\Tca\Contract\ManuscriptFile;
use Episciences\Tca\Contract\ManuscriptSourceInterface;
use Episciences\Tca\Contract\ManuscriptTemporarilyUnavailableException;
use Episciences\Tca\Contract\ManuscriptTooLargeException;
use Episciences_Paper;
use Episciences_Paper_File;
use Episciences_Paper_FilesManager;
use Episciences_PapersManager;
use Episciences_Review;
use Episciences_ReviewsManager;
use Episciences_Tools;
use Episciences\Upload\SafeFileName;
use finfo;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

/**
 * Downloads the manuscript of a paper version from its repository (arXiv, HAL, Zenodo...).
 *
 * The file is always downloaded from the repository, never read from the local backup copy of the PDF,
 * which is keyed on the DOCID only and may hold another file or a previous version of the paper.
 *
 * Datasets and software are the exception: the repository holds the data or the code, not a text.
 * The text checked is then the latest data descriptor (or software descriptor) uploaded by the author
 * to Episciences, read from the journal files. Only its final published version is deposited in an archive,
 * after the similarity check.
 * Remote files are streamed to a temporary file (never fully loaded in memory), which gives
 * the exact size required by the Turnitin upload. Redirects are followed by hand so that
 * the body of an intermediate response never ends up in the downloaded file, and so that
 * the scheme of every hop is checked.
 */
final readonly class RepositoryManuscriptSource implements ManuscriptSourceInterface
{
    public const int CONNECT_TIMEOUT = 10;
    /**
     * Upper bound, in seconds, of a whole download, redirects included: every hop gets the time left.
     * A stalled download is stopped earlier by the low speed limit.
     */
    public const int TIMEOUT = 900;
    /** A transfer slower than LOW_SPEED_LIMIT bytes per second during LOW_SPEED_TIME seconds is aborted. */
    public const int LOW_SPEED_LIMIT = 1024;
    public const int LOW_SPEED_TIME = 60;
    public const int MAX_REDIRECTS = 5;
    /** Log channel, and name of the daily log file (tca_manuscript-YYYY-MM-DD.log). */
    public const string LOG_CHANNEL = 'tca_manuscript';

    private const int MIME_SNIFF_BYTES = 8192;
    private const int FILE_NAME_MAX_LENGTH = 200;
    private const string DEFAULT_MIME_TYPE = 'application/octet-stream';
    private const string DEFAULT_EXTENSION = 'bin';
    private const string DEFAULT_FILE_NAME = 'manuscript';

    /** Statuses meaning the file is gone or not accessible without credentials: final, no retry. */
    private const array UNAVAILABLE_STATUSES = [401, 403, 404, 410];

    /** Transient statuses: retry later. */
    private const array TRANSIENT_STATUSES = [408, 425, 429];

    /**
     * cURL errors that will not go away by retrying the same URL: final, no retry.
     * 1 unsupported protocol, 3 malformed URL,
     * 51 and 60 certificate of the server not verified, 58 problem with the certificate.
     * Redirects are followed by hand (MAX_REDIRECTS), so cURL never reports too many redirects (47).
     */
    private const array PERMANENT_CURL_ERRORS = [1, 3, 51, 58, 60];

    /** MIME types too vague to describe the file: the file name extension is trusted instead. */
    private const array GENERIC_MIME_TYPES = [
        'application/octet-stream',
        'application/zip',
        'application/x-zip-compressed',
        'application/x-ole-storage',
        'application/cdfv2',
    ];

    /**
     * Not a manuscript: a login, embargo or landing page, or the JSON / XML metadata of the file, answered with a 200.
     * JATS and other XML manuscripts are detected as text/xml or application/xml, which stay accepted.
     */
    private const array NOT_A_FILE_MIME_TYPES = [
        'text/html',
        'application/xhtml+xml',
        'application/json',
        'application/ld+json',
        'application/problem+json',
        'application/vnd.api+json',
    ];

    /** Detected types too weak to overrule an announced web page or JSON document. */
    private const array WEAK_DETECTED_MIME_TYPES = ['', 'text/plain', 'application/octet-stream'];

    /** @var Closure(int): ?Episciences_Paper */
    private Closure $paperLoader;

    /** @var Closure(Episciences_Paper): ?Episciences_Paper_File */
    private Closure $mainFileLoader;

    /** @var Closure(Episciences_Paper): ?string */
    private Closure $dataDescriptorLoader;

    private PublicAddressGuard $addressGuard;

    /**
     * @param Closure(int): ?Episciences_Paper|null $paperLoader
     * @param Closure(Episciences_Paper): ?Episciences_Paper_File|null $mainFileLoader
     * @param PublicAddressGuard|null $addressGuard checks every requested URL (SSRF guard)
     * @param Closure(Episciences_Paper): ?string|null $dataDescriptorLoader path of the latest data descriptor
     *        of a dataset or software, null when there is none
     */
    public function __construct(
        private ClientInterface $httpClient,
        private LoggerInterface $logger,
        ?Closure $paperLoader = null,
        ?Closure $mainFileLoader = null,
        private int $maxBytes = ManuscriptFile::MAX_SIZE,
        private string $userAgent = '',
        ?PublicAddressGuard $addressGuard = null,
        ?Closure $dataDescriptorLoader = null
    ) {
        $this->paperLoader = $paperLoader ?? $this->defaultPaperLoader(...);
        $this->mainFileLoader = $mainFileLoader ?? $this->defaultMainFileLoader(...);
        $this->addressGuard = $addressGuard ?? new PublicAddressGuard();
        $this->dataDescriptorLoader = $dataDescriptorLoader ?? $this->defaultDataDescriptorLoader(...);
    }

    /**
     * Production wiring.
     */
    public static function create(?LoggerInterface $logger = null): self
    {
        return new self(
            new Client(),
            $logger ?? LoggerFactory::rotating(self::LOG_CHANNEL),
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
            return $this->fromDataDescriptor($paper);
        }

        $mainFile = null;

        // Repositories listing the files of a record (Zenodo...): the main file; the others: the computed paper URL
        if ($paper->hasFilesEnrichment()) {
            $mainFile = ($this->mainFileLoader)($paper);

            if ($mainFile === null) {
                $this->logUnavailable($docId, 'no file in the repository record');
                return null;
            }
        }

        return $this->fromRepository($paper, $mainFile);
    }

    private function fromRepository(Episciences_Paper $paper, ?Episciences_Paper_File $mainFile): ?ManuscriptFile
    {
        $docId = (int) $paper->getDocid();
        $url = $this->encodeUnsafeCharacters($mainFile?->getSelfLink() ?? (string) $paper->getPaperUrl());

        if (!Episciences_Tools::isHttpUrl($url)) {
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
            $body->close();
            $this->logUnavailable($docId, 'empty file', $context);
            return null;
        }

        $manuscript = $this->buildManuscript($paper, $mainFile, $body, $size, $response);

        if (!$manuscript instanceof ManuscriptFile) {
            $this->logUnavailable($docId, 'the repository returned a web page or metadata instead of a file', $context);
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
     * The data descriptor of a dataset or software, uploaded to Episciences: no download.
     *
     * @throws ManuscriptTooLargeException
     */
    private function fromDataDescriptor(Episciences_Paper $paper): ?ManuscriptFile
    {
        $docId = (int) $paper->getDocid();
        $path = ($this->dataDescriptorLoader)($paper);

        if ($path === null) {
            $this->logUnavailable($docId, 'dataset or software without data descriptor');
            return null;
        }

        $size = is_file($path) && is_readable($path) ? (int) filesize($path) : 0;

        if ($size <= 0) {
            $this->logger->warning('Data descriptor file missing, unreadable or empty', ['docid' => $docId, 'path' => $path]);
            $this->logUnavailable($docId, 'data descriptor file missing, unreadable or empty', ['path' => $path]);
            return null;
        }

        if ($size > $this->maxBytes) {
            throw new ManuscriptTooLargeException($size, $this->maxBytes);
        }

        $stream = Utils::streamFor(Utils::tryFopen($path, 'rb'));
        $fileName = $this->firstUsableFileName([basename($path)]);
        $mimeType = $this->detectMimeType($stream, null, $fileName);
        $fileName = $this->ensureExtension($fileName, $mimeType, null);

        $this->logger->info('Data descriptor read from the journal files', [
            'docid' => $docId,
            'path' => $path,
            'size' => $size,
            'mime' => $mimeType,
            'file_name' => $fileName,
        ]);

        return new ManuscriptFile($stream, $fileName, $size, $mimeType);
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
        $deadline = microtime(true) + self::TIMEOUT;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $timeLeft = $deadline - microtime(true);

            if ($timeLeft <= 0) {
                throw new ManuscriptTemporarilyUnavailableException(
                    sprintf('The download of docid %d did not complete within %d seconds.', $docId, self::TIMEOUT)
                );
            }

            $pinnedAddress = $this->pinPublicAddress($docId, $url);
            $sink = new SizeLimitedStream(Utils::streamFor(Utils::tryFopen('php://temp/maxmemory:0', 'w+b')), $this->maxBytes);

            try {
                $response = $this->send($docId, $url, $sink, $pinnedAddress, $timeLeft);
            } catch (Throwable $e) {
                $sink->close();
                throw $e;
            }

            // A transfer handler ignoring the short write of the sink still delivers a truncated body
            if ($sink->hasExceededLimit()) {
                $sink->close();
                throw new ManuscriptTooLargeException($sink->getBytesReceived(), $this->maxBytes);
            }

            $status = $response->getStatusCode();
            $location = $response->getHeaderLine('Location');

            if ($status >= 300 && $status < 400 && $location !== '') {
                $sink->close();
                $url = $this->resolveRedirect($url, $location);
                $this->logger->debug('Following a repository redirect', ['docid' => $docId, 'url' => $url]);
                continue;
            }

            $this->assertSuccessful($docId, $url, $status, $sink);
            $sink->rewind();

            return [$response, $sink, $url];
        }

        // A redirect loop or chain is a property of the repository, not a transient failure
        throw new UnavailableManuscript(sprintf('more than %d redirects', self::MAX_REDIRECTS));
    }

    /**
     * @throws UnavailableManuscript the Location header is not a valid URI (final)
     */
    private function resolveRedirect(string $url, string $location): string
    {
        try {
            return (string) UriResolver::resolve(new Uri($url), new Uri($location));
        } catch (InvalidArgumentException $e) {
            throw new UnavailableManuscript(sprintf('malformed redirect from %s', $url), 0, $e);
        }
    }

    private function send(int $docId, string $url, SizeLimitedStream $sink, string $pinnedAddress, float $timeLeft): ResponseInterface
    {
        try {
            return $this->httpClient->request('GET', $url, [
                'sink' => $sink,
                'http_errors' => false,
                'allow_redirects' => false,
                'connect_timeout' => min(self::CONNECT_TIMEOUT, $timeLeft),
                'timeout' => $timeLeft,
                'headers' => array_filter(['User-Agent' => $this->userAgent, 'Accept' => '*/*']),
                'on_headers' => $this->rejectAnnouncedOversize(...),
                // Connect to the address that was checked, never to a new DNS answer (DNS rebinding)
                'force_ip_resolve' => 'v4',
                'curl' => [
                    CURLOPT_RESOLVE => [$pinnedAddress],
                    // A large file on a slow repository is fine, a stalled transfer is not
                    CURLOPT_LOW_SPEED_LIMIT => self::LOW_SPEED_LIMIT,
                    CURLOPT_LOW_SPEED_TIME => self::LOW_SPEED_TIME,
                ],
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

    /**
     * @return ManuscriptTooLargeException|ManuscriptTemporarilyUnavailableException|UnavailableManuscript
     */
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

        $errno = $e instanceof RequestException ? ($e->getHandlerContext()['errno'] ?? null) : null;

        $this->logger->warning('Manuscript download failed', ['docid' => $docId, 'url' => $url, 'errno' => $errno, 'error' => $e->getMessage()]);

        if (in_array($errno, self::PERMANENT_CURL_ERRORS, true)) {
            return new UnavailableManuscript(sprintf('transfer error %d from %s', $errno, $url), 0, $e);
        }

        return new ManuscriptTemporarilyUnavailableException(
            sprintf('The repository of docid %d could not be reached.', $docId),
            0,
            $e
        );
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

        if ($status >= 500 || in_array($status, self::TRANSIENT_STATUSES, true)) {
            throw new ManuscriptTemporarilyUnavailableException(sprintf('The repository of docid %d answered HTTP %d.', $docId, $status));
        }

        throw new UnavailableManuscript(sprintf('unexpected HTTP %d from %s', $status, $url));
    }

    /**
     * @return ManuscriptFile|null null when the content is a web page or metadata, not a file
     */
    private function buildManuscript(
        Episciences_Paper $paper,
        ?Episciences_Paper_File $mainFile,
        StreamInterface $stream,
        int $size,
        ?ResponseInterface $response
    ): ?ManuscriptFile {
        $fileName = $this->firstUsableFileName([
            $this->contentDispositionFileName($response),
            $mainFile?->getFileName(),
            sprintf('%sv%s', $paper->getIdentifier(), $paper->getVersion()),
        ]);

        $mimeType = $this->detectMimeType($stream, $response, $fileName);

        if ($this->isNotAFile($mimeType, $response)) {
            $stream->close();
            return null;
        }

        $fileName = $this->ensureExtension($fileName, $mimeType, $mainFile?->getFileType());

        return new ManuscriptFile($stream, $fileName, $size, $mimeType);
    }

    /**
     * A web page or a metadata document instead of the file. The announced Content-Type is trusted
     * only when the content itself says nothing more precise (an HTML error page sniffed as text/plain).
     */
    private function isNotAFile(string $mimeType, ?ResponseInterface $response): bool
    {
        if (in_array($mimeType, self::NOT_A_FILE_MIME_TYPES, true)) {
            return true;
        }

        return in_array($mimeType, self::WEAK_DETECTED_MIME_TYPES, true)
            && in_array($this->announcedMimeType($response), self::NOT_A_FILE_MIME_TYPES, true);
    }

    private function announcedMimeType(?ResponseInterface $response): string
    {
        return strtolower(trim(explode(';', $response?->getHeaderLine('Content-Type') ?? '')[0]));
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

        $announced = $this->announcedMimeType($response);

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

        $parameters = $this->contentDispositionParameters($header);
        $extended = $this->decodeExtendedFileName((string) ($parameters['filename*'] ?? ''));

        if ($extended !== null) {
            return $extended;
        }

        $name = $parameters['filename'] ?? '';

        return $name !== '' ? $name : null;
    }

    /**
     * The parameters of a Content-Disposition header, keyed by lowercased name (RFC 6266).
     * Not Guzzle's Header::parse(): it splits on every "=" and on unquoted commas,
     * which truncates names such as "a=b final.pdf" or "Smith, J. paper.pdf".
     *
     * @return array<string, string>
     */
    private function contentDispositionParameters(string $header): array
    {
        preg_match_all('/;\s*([^\s=;]+)\s*=\s*("(?:[^"\\\\]|\\\\.)*"|[^;]*)/', $header, $matches, PREG_SET_ORDER);

        $parameters = [];

        foreach ($matches as [, $name, $value]) {
            $value = trim($value);

            if (strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
                // Quoted-string: drop the quotes and the backslash escapes
                $value = (string) preg_replace('/\\\\(.)/s', '$1', substr($value, 1, -1));
            }

            $parameters[strtolower($name)] = $value;
        }

        return $parameters;
    }

    /**
     * RFC 5987 / RFC 6266 filename*: charset'language'percent-encoded-name.
     * Returns null when the value is absent, malformed or in a charset that cannot be read,
     * so that the plain filename parameter is used instead.
     */
    private function decodeExtendedFileName(string $value): ?string
    {
        if (!preg_match("/^([\w-]+)'[^']*'(.+)$/", $value, $matches)) {
            return null;
        }

        $name = rawurldecode($matches[2]);

        $name = match (strtolower($matches[1])) {
            'utf-8' => $name,
            'iso-8859-1' => mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1'),
            default => null,
        };

        return ($name !== null && $name !== '' && mb_check_encoding($name, 'UTF-8')) ? $name : null;
    }

    /**
     * Keeps the accents (Turnitin receives the name URL-encoded) but drops path separators and control characters.
     */
    /**
     * The first candidate that is still a usable name once sanitized: a name announced by the server
     * but unreadable (raw ISO-8859-1, dots only) gives way to the next candidate.
     *
     * @param list<string|null> $candidates in order of preference
     */
    private function firstUsableFileName(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $fileName = $candidate === null ? null : $this->sanitizeFileName($candidate);

            if ($fileName !== null) {
                return $fileName;
            }
        }

        return self::DEFAULT_FILE_NAME;
    }

    /**
     * @return string|null null when nothing usable is left
     */
    private function sanitizeFileName(string $fileName): ?string
    {
        if (!mb_check_encoding($fileName, 'UTF-8')) {
            return null;
        }

        $fileName = basename(str_replace('\\', '/', $fileName));
        $fileName = (string) preg_replace('/[\x00-\x1F\x7F\/:]+/u', '_', $fileName);
        $fileName = trim($fileName, " .\t");

        return $fileName === '' ? null : mb_substr($fileName, -self::FILE_NAME_MAX_LENGTH);
    }

    /**
     * SSRF guard: file links and redirect targets come from third parties (repository APIs, remote servers).
     * Returns the CURLOPT_RESOLVE entry pinning the request to the checked public address.
     *
     * @throws UnavailableManuscript the URL is not allowed (final)
     * @throws ManuscriptTemporarilyUnavailableException the host name lookup failed
     */
    private function pinPublicAddress(int $docId, string $url): string
    {
        try {
            return $this->addressGuard->pin(new Uri($url));
        } catch (RefusedAddressException $e) {
            $this->logger->warning('Refused a repository URL', ['docid' => $docId, 'url' => $url, 'reason' => $e->getMessage()]);
            throw new UnavailableManuscript(sprintf('refused URL %s: %s', $url, $e->getMessage()), 0, $e);
        } catch (UnresolvedHostException $e) {
            throw new ManuscriptTemporarilyUnavailableException(sprintf('The repository host of docid %d could not be resolved.', $docId), 0, $e);
        }
    }

    /**
     * Percent-encodes the spaces, control characters, non-ASCII bytes and quotes and angle brackets
     * of a file link (a repository may store "files/l'article final.pdf"), as an HTTP client would.
     * Episciences_Tools::isHttpUrl() refuses the link otherwise.
     */
    private function encodeUnsafeCharacters(string $url): string
    {
        return (string) preg_replace_callback(
            '/[^\x21-\x7E]|["\'<>]/',
            static fn(array $byte): string => rawurlencode($byte[0]),
            trim($url)
        );
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
     * data/{journal code}/files/{docid}/dd/{file name}: built from the journal of the paper,
     * REVIEW_FILES_PATH being defined only for the requests of a journal site.
     */
    private function defaultDataDescriptorLoader(Episciences_Paper $paper): ?string
    {
        $paper->loadDataDescriptors();
        $fileName = $paper->getLatestDataDescriptor()?->getFile()->getName();

        // The stored name is joined to a directory: no directory part allowed
        if ($fileName === null || !SafeFileName::isBare($fileName)) {
            return null;
        }

        $review = Episciences_ReviewsManager::find($paper->getRvid());

        if (!$review instanceof Episciences_Review) {
            return null;
        }

        return sprintf(
            '%s/../data/%s/files/%d/%s/%s',
            APPLICATION_PATH,
            $review->getCode(),
            (int) $paper->getDocid(),
            FileManager::DD_SOURCE,
            $fileName
        );
    }
}

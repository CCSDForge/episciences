<?php

declare(strict_types=1);

require_once __DIR__ . '/Next/BootstrapsNextEnvironment.php';

use Episciences\Next\Messenger\HandlerFactory;
use Episciences\Next\Messenger\Message\RevalidateTagMessage;
use Episciences\Next\RevalidationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Throwable;

/**
 * Immediately trigger Next.js cache revalidation for a specific journal and
 * one or more tags. Bypasses the queue by default — for manual/urgent use
 * and smoke testing — or enqueues instead with --queue (symmetry with
 * solr:index, which enqueues by default and has a --sync option).
 *
 * Usage: php scripts/console.php next:revalidate-cache epijinfo article-42
 *        php scripts/console.php next:revalidate-cache epijinfo article-42 volumes-epijinfo
 *        php scripts/console.php next:revalidate-cache --queue epijinfo article-42
 */
class RevalidateNextCacheCommand extends Command
{
    use BootstrapsNextEnvironment;

    protected static $defaultName = 'next:revalidate-cache';

    protected function configure(): void
    {
        $this
            ->setDescription('Immediately trigger Next.js cache revalidation for a journal and one or more tags (bypasses the queue by default; see --queue).')
            ->addArgument('rvcode', InputArgument::REQUIRED, 'Journal code (e.g. epijinfo)')
            ->addArgument('tag', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'One or more cache tags to invalidate (e.g. article-42)')
            ->addOption('queue', null, InputOption::VALUE_NONE, 'Enqueue the tag(s) instead of POSTing immediately.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rvcode = (string)$input->getArgument('rvcode');
        /** @var list<string> $tags */
        $tags = $input->getArgument('tag');

        $this->bootstrapNextEnvironment();

        if ((bool)$input->getOption('queue')) {
            foreach ($tags as $tag) {
                RevalidationService::enqueueTag($rvcode, (string)$tag);
            }
            $io->success(sprintf('Enqueued %d tag(s) for journal <info>%s</info>.', count($tags), $rvcode));

            return Command::SUCCESS;
        }

        if (!defined('NEXT_BASE_URL') || NEXT_BASE_URL === '') {
            $io->error('NEXT_BASE_URL is not defined in config/pwd.json.');

            return Command::FAILURE;
        }

        if (!defined('EPISCIENCES_ENABLE_NEXT_FRONT') || !EPISCIENCES_ENABLE_NEXT_FRONT) {
            // The handler itself no-ops when the flag is off (see
            // RevalidateTagMessageHandler) — warn here so this doesn't look
            // like a silent success for an explicit, manual command.
            $io->warning('EPISCIENCES_ENABLE_NEXT_FRONT is off — nothing will actually be sent.');
        }

        $io->text('Endpoint: ' . rtrim(NEXT_BASE_URL, '/') . '/api/revalidate');
        $handler = HandlerFactory::createRevalidateTagHandler();
        $exitCode = Command::SUCCESS;

        foreach ($tags as $tag) {
            $tag = (string)$tag;
            $io->text("Revalidating tag <info>{$tag}</info> for journal <info>{$rvcode}</info>");

            try {
                $handler(new RevalidateTagMessage($rvcode, $tag));
                $io->success(sprintf('Revalidation succeeded for tag "%s".', $tag));
            } catch (UnrecoverableMessageHandlingException $e) {
                $io->error(sprintf('Permanent failure for tag "%s": %s', $tag, $e->getMessage()));
                $exitCode = Command::FAILURE;
            } catch (Throwable $e) {
                $io->warning(sprintf('Transient failure for tag "%s": %s', $tag, $e->getMessage()));
                $exitCode = Command::FAILURE;
            }
        }

        return $exitCode;
    }
}

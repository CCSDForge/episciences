<?php
declare(strict_types=1);

use Episciences\Journal\Provisioning\AdminRoleAssigner;
use Episciences\Journal\Provisioning\DataDirectoryProvisioner;
use Episciences\Journal\Provisioning\JournalCreator;
use Episciences\Journal\Provisioning\JournalSpec;
use Episciences\Journal\Provisioning\PagesCloner;
use Episciences\Journal\Provisioning\ReviewRowWriter;
use Episciences\Journal\Provisioning\SettingsCloner;
use Episciences\Journal\Provisioning\WebsiteCloner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: create a new journal by cloning a template journal.
 *
 * Creating a journal today is an entirely manual, untracked procedure: a hand-written SQL
 * INSERT into REVIEW, a `cp -rp` of a data directory, an Apache vhost, and page-by-page
 * back-office data entry. This command replaces the database/data-directory half of that with
 * one auditable run: it inserts the REVIEW row (nothing else in the codebase does — see
 * Episciences\Journal\Provisioning\ReviewRowWriter), clones REVIEW_SETTING/menu/pages/appearance
 * from an existing "template" journal (excluding editorial identity, outgoing-notification
 * settings and secrets — see SettingsCloner/DataDirectoryProvisioner), creates the data
 * directory tree, and grants an existing user the administrator role.
 *
 * What it does NOT do (left as manual steps — see docs/journal-provisioning.md): the Apache
 * vhost, DNS, a Matomo site, enabling the journal, and filling in editorial identity
 * (ISSN, contacts, description).
 *
 * bootstrap() ordering is the one subtlety worth reading before touching this file: unlike
 * every other command's bootstrap() (see ImportPapersCommand), this one must NOT call
 * defineJournalConstants(). That function defines RVCODE, RVID and REVIEW_PATH as process-wide
 * PHP constants — and REVIEW_PATH is built with
 * `realpath(APPLICATION_PATH . '/../data/' . $rvCode) . '/'`, which resolves to just '/' when
 * the directory does not exist yet (a trap already hit once on this project — see
 * public/const.php). Calling defineJournalConstants() with no argument (the pattern every
 * other bootstrap() uses) would also permanently freeze RVCODE = null for this whole process,
 * before the new journal's code is even known. So this command boots the database connection
 * only, and JournalCreator defines the journal constants itself once the REVIEW row and the
 * data directory both exist (see JournalCreator's docblock for the exact ordering).
 */
final class CreateJournalCommand extends Command
{
    protected static $defaultName = 'journal:create';

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new journal by cloning settings, menu, pages and appearance from a template journal.')
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'New journal code (rvcode)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'New journal name')
            ->addOption('subtitle', null, InputOption::VALUE_REQUIRED, 'New journal subtitle')
            ->addOption('template-rvcode', null, InputOption::VALUE_REQUIRED, 'Existing journal code to clone settings/menu/pages/appearance from')
            ->addOption('admin-uid', null, InputOption::VALUE_REQUIRED, 'UID of the existing user to make administrator of the new journal')
            ->addOption('piwikid', null, InputOption::VALUE_REQUIRED, 'Matomo (Piwik) site id (default: 0, no Matomo API call is ever made)')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Initial status: 0 (disabled, default) or 1 (enabled)')
            ->addOption('new-front', null, InputOption::VALUE_REQUIRED, 'Serve through the new front-end: yes or no (default: no)')
            ->addOption('reset-doi', null, InputOption::VALUE_NONE, 'Reset cloned DOI settings to manual mode with an empty prefix')
            ->addOption('complete', null, InputOption::VALUE_NONE, 'Finish provisioning an existing journal instead of refusing because its code already exists')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the plan without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');
        $complete = (bool)$input->getOption('complete');

        $io->title('Journal creation');
        $this->bootstrap();

        try {
            $spec = JournalSpec::fromInput($input, $io);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->section('Summary');
        $io->definitionList(
            ['Code' => $spec->code],
            ['Name' => $spec->name],
            ['Subtitle' => $spec->subtitle !== '' ? $spec->subtitle : '(none)'],
            ['Template journal' => $spec->templateRvcode],
            ['Admin UID' => (string)$spec->adminUid],
            ['Matomo site id' => (string)$spec->piwikId],
            ['Status' => $spec->status === Episciences_Review::ENABLED ? 'enabled' : 'disabled'],
            ['New front-end' => $spec->isNewFrontSwitched ? 'yes' : 'no'],
            ['Reset DOI settings' => $spec->resetDoi ? 'yes' : 'no'],
            ['Mode' => $complete ? 'complete (finish an existing journal)' : 'create'],
        );

        if (!$dryRun && !$io->confirm('Proceed?', !$input->isInteractive())) {
            $io->warning('Aborted.');
            return Command::SUCCESS;
        }

        $creator = new JournalCreator(
            new ReviewRowWriter(),
            new SettingsCloner(),
            new DataDirectoryProvisioner(),
            new WebsiteCloner(),
            new PagesCloner(),
            new AdminRoleAssigner()
        );

        try {
            $report = $creator->create($spec, $dryRun, $complete);
        } catch (Throwable $e) {
            $io->error('Journal creation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->section($dryRun ? 'Plan' : 'Result');
        $io->table(['Step', 'Detail'], $report->toRows());

        foreach ($report->warnings() as $warning) {
            $io->warning($warning);
        }

        if (!$dryRun) {
            $io->success("Journal '{$spec->code}' created.");
            $io->note(sprintf(
                "Remaining manual steps: Apache vhost, DNS entry, Matomo site (if any), enabling the journal, " .
                "and filling in editorial identity (ISSN, contacts, description). See docs/journal-provisioning.md.\n" .
                "To seed sandbox demo content, run: php scripts/console.php journal:seed-demo --rvcode=%s --uid=%d",
                $spec->code,
                $spec->adminUid
            ));
        }

        return Command::SUCCESS;
    }

    private function bootstrap(): void
    {
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', realpath(__DIR__ . '/../application'));
        }
        require_once __DIR__ . '/../public/const.php';
        require_once __DIR__ . '/../public/bdd_const.php';

        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();

        // Deliberately NOT calling defineJournalConstants() here — see this class's docblock.

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';

        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));

        if (!Zend_Registry::isRegistered('Zend_Translate')) {
            Zend_Registry::set('Zend_Translate', new Zend_Translate([
                'adapter' => Zend_Translate::AN_ARRAY,
                'content' => ['' => ''],
                'locale' => 'en',
            ]));
        }
        Zend_Registry::isRegistered('lang') || Zend_Registry::set('lang', 'en');
    }
}

<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/GenerateUsersCommand.php';
require_once __DIR__ . '/InitDevUsersCommand.php';
require_once __DIR__ . '/CreateBotUserCommand.php';
require_once __DIR__ . '/ExtractBiblioRefsCommand.php';
require_once __DIR__ . '/GetCitationsDataCommand.php';
require_once __DIR__ . '/GetCreatorDataCommand.php';
require_once __DIR__ . '/GetLicenceDataCommand.php';
require_once __DIR__ . '/GetLinkDataCommand.php';
require_once __DIR__ . '/GetFundingDataCommand.php';
require_once __DIR__ . '/GetClassificationJelCommand.php';
require_once __DIR__ . '/ClearOpenAireCacheCommand.php';
require_once __DIR__ . '/GetClassificationMscCommand.php';
require_once __DIR__ . '/GetZbReviewsCommand.php';
require_once __DIR__ . '/GenerateSitemapCommand.php';
require_once __DIR__ . '/RegenerateWebsiteHeadersCommand.php';
require_once __DIR__ . '/MergePdfVolCommand.php';
require_once __DIR__ . '/CreateDoajVolumeExportsCommand.php';
require_once __DIR__ . '/CheckDoajJournalsCommand.php';
require_once __DIR__ . '/ZbjatsZipperCommand.php';
require_once __DIR__ . '/ImportSectionsCommand.php';
require_once __DIR__ . '/ImportVolumesCommand.php';
require_once __DIR__ . '/ImportPapersCommand.php';
require_once __DIR__ . '/ExportPapersCommand.php';
require_once __DIR__ . '/ImportRefPpsCommand.php';
require_once __DIR__ . '/DownloadRefPpsCommand.php';
require_once __DIR__ . '/UpdateCounterRobotsListCommand.php';
require_once __DIR__ . '/ProcessStatTempCommand.php';
require_once __DIR__ . '/ImportApacheLogsCommand.php';
require_once __DIR__ . '/UpdateGeoIpCommand.php';
require_once __DIR__ . '/GenerateDownloadKpiCommand.php';
require_once __DIR__ . '/UpdatePapersDocumentCommand.php';
require_once __DIR__ . '/CleanHalRecordDescriptionsCommand.php';
require_once __DIR__ . '/UpdateTranslationsCommand.php';
require_once __DIR__ . '/GetDoiCommand.php';
require_once __DIR__ . '/Command/AbstractCommand.php';
require_once __DIR__ . '/Command/Bootstrapper.php';
require_once __DIR__ . '/UpdateLicensesCommand.php';
require_once __DIR__ . '/ImportSpdxLicenseListCommand.php';
require_once __DIR__ . '/NormalizeUserAffiliationsCommand.php';
require_once __DIR__ . '/RevalidateNextCacheCommand.php';
require_once __DIR__ . '/AnonymizeSuLogsCommand.php';

// Solr indexing commands
require_once __DIR__ . '/SolrIndexCommand.php';
require_once __DIR__ . '/SolrDeleteCommand.php';

// Messenger queue commands (Solr indexing, Next.js revalidation)
require_once __DIR__ . '/EpisciencesWorkerCommand.php';
require_once __DIR__ . '/EpisciencesQueueCommand.php';

// COAR Notify inbox processing command
require_once __DIR__ . '/ProcessInboxNotificationsCommand.php';

// Journal provisioning commands
require_once __DIR__ . '/CreateJournalCommand.php';
require_once __DIR__ . '/SeedJournalDemoCommand.php';

use Symfony\Component\Console\Application;

$application = new Application('Episciences CLI', '1.0.0');

// Register commands
$application->addCommand(new GenerateUsersCommand());
$application->addCommand(new InitDevUsersCommand());
$application->addCommand(new CreateBotUserCommand());
$application->addCommand(new AnonymizeSuLogsCommand());

// Enrichment commands
$application->addCommand(new ExtractBiblioRefsCommand());
$application->addCommand(new GetCitationsDataCommand());
$application->addCommand(new GetCreatorDataCommand());
$application->addCommand(new GetLicenceDataCommand());
$application->addCommand(new GetLinkDataCommand());
$application->addCommand(new GetFundingDataCommand());
$application->addCommand(new GetClassificationJelCommand());
$application->addCommand(new ClearOpenAireCacheCommand());
$application->addCommand(new GetClassificationMscCommand());
$application->addCommand(new GetZbReviewsCommand());

// Sitemap commands
$application->addCommand(new GenerateSitemapCommand());
$application->addCommand(new RegenerateWebsiteHeadersCommand());

// Volume commands
$application->addCommand(new MergePdfVolCommand());

// DOAJ commands
$application->addCommand(new CreateDoajVolumeExportsCommand());
$application->addCommand(new CheckDoajJournalsCommand());

// zbJATS commands
$application->addCommand(new ZbjatsZipperCommand());

// Import commands
$application->addCommand(new ImportSectionsCommand());
$application->addCommand(new ImportVolumesCommand());
$application->addCommand(new ImportPapersCommand());
$application->addCommand(new ExportPapersCommand());
$application->addCommand(new ImportRefPpsCommand());
$application->addCommand(new DownloadRefPpsCommand());

// Stats commands
$application->addCommand(new UpdateCounterRobotsListCommand());
$application->addCommand(new ImportApacheLogsCommand());
$application->addCommand(new ProcessStatTempCommand());
$application->addCommand(new GenerateDownloadKpiCommand());

// DOI commands
$application->addCommand(new GetDoiCommand());

// User commands
$application->addCommand(new NormalizeUserAffiliationsCommand());

// GeoIP commands
$application->addCommand(new UpdateGeoIpCommand());

// Papers commands
$application->addCommand(new UpdatePapersDocumentCommand());
$application->addCommand(new UpdateLicensesCommand());
$application->addCommand(new ImportSpdxLicenseListCommand());
$application->addCommand(new CleanHalRecordDescriptionsCommand());

// Translation commands
$application->addCommand(new UpdateTranslationsCommand());

// Solr indexing commands
$application->addCommand(new SolrIndexCommand());
$application->addCommand(new SolrDeleteCommand());

// Messenger queue commands (Solr indexing, Next.js revalidation)
$application->addCommand(new EpisciencesWorkerCommand());
$application->addCommand(new EpisciencesQueueCommand());

// Next.js cache revalidation commands
$application->addCommand(new RevalidateNextCacheCommand());

// COAR Notify inbox processing command
$application->addCommand(new ProcessInboxNotificationsCommand());

// Journal provisioning commands
$application->addCommand(new CreateJournalCommand());
$application->addCommand(new SeedJournalDemoCommand());

$application->run();

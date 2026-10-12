<?php

declare(strict_types=1);

namespace unit\modules\common\views;

use DateTimeImmutable;
use Episciences\Maintenance\ReadOnlyMode;
use PHPUnit\Framework\TestCase;
use Zend_Registry;
use Zend_View;

/**
 * Rendering tests for layout/commons/maintenance-banner.phtml: a single banner, by priority
 * read-only mode in progress, upcoming read-only maintenance, then the free RENDER_ALERT_BANNER message.
 */
final class MaintenanceBannerPartialTest extends TestCase
{
    private const ALERT_FR = 'Arrêt de service prévu';
    private const ALERT_EN = 'Planned service interruption';

    private Zend_View $view;

    private bool $langWasRegistered = false;

    private mixed $previousLang = null;

    protected function setUp(): void
    {
        $this->view = new Zend_View();
        $this->view->setScriptPath(APPLICATION_PATH . '/modules/common/views/layout');
        $this->view->alertBannerConfig = ['STATUS' => 0, 'MESSAGE' => ['FR' => self::ALERT_FR, 'DEFAULT_LANG' => self::ALERT_EN]];

        $this->langWasRegistered = Zend_Registry::isRegistered('lang');
        $this->previousLang = $this->langWasRegistered ? Zend_Registry::get('lang') : null;
        Zend_Registry::set('lang', 'fr');

        ReadOnlyMode::setOverride(false);
        ReadOnlyMode::setScheduleOverride(null, null);
    }

    protected function tearDown(): void
    {
        ReadOnlyMode::setOverride(null);
        ReadOnlyMode::setScheduleOverride(null, null);

        if ($this->langWasRegistered) {
            Zend_Registry::set('lang', $this->previousLang);
        } else {
            Zend_Registry::getInstance()->offsetUnset('lang');
        }
    }

    public function testNothingIsRenderedWithoutMaintenance(): void
    {
        self::assertSame('', trim($this->render()));
    }

    public function testReadOnlyBannerIsAnAccessibleStatus(): void
    {
        ReadOnlyMode::setOverride(true);

        $html = $this->render();

        self::assertStringContainsString('role="status"', $html);
        self::assertStringContainsString('Maintenance en cours', $html);
        self::assertStringNotContainsString('read-only-schedule', $html);
    }

    public function testReadOnlyBannerShowsThePlannedStartAndEnd(): void
    {
        ReadOnlyMode::setOverride(true);
        ReadOnlyMode::setScheduleOverride('2026-10-20T08:00:00+02:00', '2026-10-20T12:30:00+02:00');

        self::assertStringContainsString(
            sprintf(
                'Maintenance prévue du %1$s au %2$s.',
                ReadOnlyMode::formatDateTime(new DateTimeImmutable('2026-10-20T08:00:00+02:00'), 'fr'),
                ReadOnlyMode::formatDateTime(new DateTimeImmutable('2026-10-20T12:30:00+02:00'), 'fr')
            ),
            $this->render()
        );
    }

    public function testReadOnlyBannerShowsThePlannedEndAlone(): void
    {
        ReadOnlyMode::setOverride(true);
        ReadOnlyMode::setScheduleOverride(null, '2026-10-20T12:30:00+02:00');

        $html = $this->render();

        self::assertStringContainsString('Fin de maintenance prévue : ', $html);
        self::assertStringContainsString('(UTC+02:00)', $html);
    }

    public function testReadOnlyBannerReplacesTheFreeAnnouncement(): void
    {
        ReadOnlyMode::setOverride(true);
        $this->view->alertBannerConfig['STATUS'] = 1;

        $html = $this->render();

        self::assertStringContainsString('Maintenance en cours', $html);
        self::assertStringNotContainsString(self::ALERT_FR, $html);
    }

    public function testUpcomingReadOnlyMaintenanceIsAnnouncedFromItsPlannedStart(): void
    {
        $start = (new DateTimeImmutable('+2 days'))->format(DATE_ATOM);
        $end = (new DateTimeImmutable('+2 days +4 hours'))->format(DATE_ATOM);
        ReadOnlyMode::setScheduleOverride($start, $end);
        $this->view->alertBannerConfig['STATUS'] = 1;

        $html = $this->render();

        self::assertStringContainsString('Maintenance à venir', $html);
        self::assertStringContainsString('Maintenance prévue du ', $html);
        self::assertStringNotContainsString(self::ALERT_FR, $html);
    }

    public function testUpcomingMaintenanceWithoutEndShowsItsPlannedStart(): void
    {
        ReadOnlyMode::setScheduleOverride((new DateTimeImmutable('+2 days'))->format(DATE_ATOM), null);

        self::assertStringContainsString('Début de maintenance prévu : ', $this->render());
    }

    public function testPastPlannedStartIsNotAnnounced(): void
    {
        ReadOnlyMode::setScheduleOverride((new DateTimeImmutable('-1 day'))->format(DATE_ATOM), null);

        self::assertSame('', trim($this->render()));
    }

    public function testFreeAnnouncementIsShownInTheInterfaceLanguage(): void
    {
        $this->view->alertBannerConfig['STATUS'] = 1;

        self::assertStringContainsString(self::ALERT_FR, $this->render());

        Zend_Registry::set('lang', 'en');
        self::assertStringContainsString(self::ALERT_EN, $this->render());
    }

    public function testMissingFreeAnnouncementConfigurationRendersNothing(): void
    {
        $this->view->alertBannerConfig = [];

        self::assertSame('', trim($this->render()));
    }

    private function render(): string
    {
        return html_entity_decode($this->view->render('commons/maintenance-banner.phtml'), ENT_QUOTES, 'UTF-8');
    }
}

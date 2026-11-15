<?php

use Episciences\Volume\CrossrefVolumeExporter;
use PHPUnit\Framework\TestCase;

/**
 * Crossref XML of a proceedings volume, compared with the structure of
 * scripts/importData/mbj51.xml (the Crossref 5.3.1 XSD is not available offline).
 *
 * @covers \Episciences\Volume\CrossrefVolumeExporter
 */
final class CrossrefVolumeExporterTest extends TestCase
{
    private const NS = 'http://www.crossref.org/schema/5.3.1';
    private const DOI = '10.46298/mbj.proceedings.51';

    /**
     * @param array<string, string> $settings
     * @param array<string, string>|null $titles
     */
    private function makeVolume(array $settings = [], ?array $titles = null, ?string $earliestPublication = null): Episciences_Volume
    {
        $volume = new class($earliestPublication) extends Episciences_Volume {
            public function __construct(private ?string $earliestPublication)
            {
                parent::__construct();
            }

            public function getEarliestPublicationDateFromVolume(): ?string
            {
                return $this->earliestPublication;
            }
        };

        $volume->setVid(1134);
        $volume->setRvid(42);
        $volume->setTitles($titles ?? [
            'fr' => 'Actes du 51e congrès de la Société de Biomécanique',
            'en' => 'Proceedings of the 51st congress of the Société de Biomécanique',
        ]);
        $volume->setSettings(array_merge([
            Episciences_Volume::VOLUME_IS_PROCEEDING => '1',
            Episciences_Volume::VOLUME_CONFERENCE_NAME => '51st congress of the Société de Biomécanique',
            Episciences_Volume::VOLUME_CONFERENCE_THEME => 'Biomechanics in Growth',
            Episciences_Volume::VOLUME_CONFERENCE_ACRONYM => '',
            Episciences_Volume::VOLUME_CONFERENCE_NUMBER => '51',
            Episciences_Volume::VOLUME_CONFERENCE_LOCATION => 'Montpellier, France',
            Episciences_Volume::VOLUME_CONFERENCE_START_DATE => '2026-10-27',
            Episciences_Volume::VOLUME_CONFERENCE_END_DATE => '2026-10-29',
            Episciences_Volume::VOLUME_CONFERENCE_DOI => self::DOI,
        ], $settings));
        $volume->setEditors([]);
        $volume->markEditorsLoaded(true);

        return $volume;
    }

    private function makeJournal(string $publisher = 'Société de Biomécanique', string $place = 'France'): Episciences_Review
    {
        $journal = new Episciences_Review();
        $journal->setRvid(42);
        $journal->setCode('mbj');
        $journal->setName('Music & Biomechanics Journal');
        $journal->setSetting(Episciences_Review::SETTING_JOURNAL_PUBLISHER, $publisher);
        $journal->setSetting(Episciences_Review::SETTING_JOURNAL_PUBLISHER_LOC, $place);

        return $journal;
    }

    private function makeExporter(bool $newFront = true): CrossrefVolumeExporter
    {
        return new CrossrefVolumeExporter(
            static fn(int $rvid): bool => $newFront,
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-11-15 10:20:30')
        );
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        self::assertTrue($dom->loadXML($xml), 'The export must be well-formed XML');
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('c', self::NS);

        return $xpath;
    }

    private function text(DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);

        return ($nodes !== false && $nodes->length > 0) ? $nodes->item(0)->textContent : null;
    }

    public function testExportMatchesTheReferenceStructure(): void
    {
        $xml = $this->makeExporter()->export($this->makeVolume(), $this->makeJournal(), true);

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);

        $xpath = $this->xpath($xml);
        $conference = '/c:doi_batch/c:body/c:conference';

        self::assertSame('5.3.1', $this->text($xpath, '/c:doi_batch/@version'));
        self::assertSame(DOI_EMAIL_CONTACT, $this->text($xpath, '//c:head/c:depositor/c:email_address'));
        self::assertSame('20261115102030000', $this->text($xpath, '//c:head/c:timestamp'));
        self::assertSame('51st congress of the Société de Biomécanique', $this->text($xpath, "$conference/c:event_metadata/c:conference_name"));
        self::assertSame('Biomechanics in Growth', $this->text($xpath, "$conference/c:event_metadata/c:conference_theme"));
        self::assertNull($this->text($xpath, "$conference/c:event_metadata/c:conference_acronym"), 'Empty acronym is omitted');
        self::assertSame('51', $this->text($xpath, "$conference/c:event_metadata/c:conference_number"));
        self::assertSame('Montpellier, France', $this->text($xpath, "$conference/c:event_metadata/c:conference_location"));
        self::assertSame('27', $this->text($xpath, "$conference/c:event_metadata/c:conference_date/@start_day"));
        self::assertSame('10', $this->text($xpath, "$conference/c:event_metadata/c:conference_date/@end_month"));
        self::assertSame('29', $this->text($xpath, "$conference/c:event_metadata/c:conference_date/@end_day"));

        $proceedings = "$conference/c:proceedings_metadata";
        self::assertSame('en', $this->text($xpath, "$proceedings/@language"));
        self::assertSame('Proceedings of the 51st congress of the Société de Biomécanique', $this->text($xpath, "$proceedings/c:proceedings_title"));
        self::assertSame('Société de Biomécanique', $this->text($xpath, "$proceedings/c:publisher/c:publisher_name"));
        self::assertSame('France', $this->text($xpath, "$proceedings/c:publisher/c:publisher_place"));
        self::assertSame('2026', $this->text($xpath, "$proceedings/c:publication_date/c:year"));
        self::assertSame('simple_series', $this->text($xpath, "$proceedings/c:noisbn/@reason"));
        self::assertSame(self::DOI, $this->text($xpath, "$proceedings/c:doi_data/c:doi"));
        self::assertSame('https://mbj.' . DOMAIN . '/volumes/1134', $this->text($xpath, "$proceedings/c:doi_data/c:resource"));
        self::assertNull($this->text($xpath, "$conference/c:contributors"), 'No editors, no contributors');
    }

    public function testPublicExportDoesNotDiscloseTheDepositorEmail(): void
    {
        $xpath = $this->xpath($this->makeExporter()->export($this->makeVolume(), $this->makeJournal()));

        self::assertSame('noreply@' . DOMAIN, $this->text($xpath, '//c:head/c:depositor/c:email_address'));
    }

    public function testResourceUrlFollowsTheFrontOfTheJournal(): void
    {
        $xpath = $this->xpath($this->makeExporter(false)->export($this->makeVolume(), $this->makeJournal()));

        self::assertSame('https://mbj.' . DOMAIN . '/volume/view/id/1134', $this->text($xpath, '//c:doi_data/c:resource'));
        self::assertSame('https://example.org/volumes/7', CrossrefVolumeExporter::getResourceUrl('https://example.org/', 7, true));
        self::assertSame('https://example.org/volume/view/id/7', CrossrefVolumeExporter::getResourceUrl('http://example.org', 7, false));
    }

    public function testPublisherFallsBackToTheJournalNameWithoutPlace(): void
    {
        $xpath = $this->xpath($this->makeExporter()->export($this->makeVolume(), $this->makeJournal('', '')));

        self::assertSame('Music & Biomechanics Journal', $this->text($xpath, '//c:publisher/c:publisher_name'));
        self::assertNull($this->text($xpath, '//c:publisher/c:publisher_place'));
    }

    public function testTitleUsesTheFirstLanguageWithoutEnglish(): void
    {
        $volume = $this->makeVolume([], ['fr' => 'Actes', 'de' => 'Tagungsband']);
        $xpath = $this->xpath($this->makeExporter()->export($volume, $this->makeJournal()));

        self::assertSame('fr', $this->text($xpath, '//c:proceedings_metadata/@language'));
        self::assertSame('Actes', $this->text($xpath, '//c:proceedings_title'));
        self::assertSame(['title' => '', 'language' => 'en'], CrossrefVolumeExporter::getProceedingsTitle(null));
    }

    public function testPublicationYearComesFromTheEarliestPublishedPaper(): void
    {
        $volume = $this->makeVolume([], null, '2025-03-04 12:00:00');

        self::assertSame('2025', $this->makeExporter()->getPublicationYear($volume));
    }

    public function testConferenceDateNeedsTwoValidDates(): void
    {
        $xpath = $this->xpath($this->makeExporter()->export(
            $this->makeVolume([Episciences_Volume::VOLUME_CONFERENCE_END_DATE => '']),
            $this->makeJournal()
        ));

        self::assertNull($this->text($xpath, '//c:conference_date'));
        self::assertNull(CrossrefVolumeExporter::getConferenceDate('2026-02-30x', '2026-03-01'));
        self::assertNull(CrossrefVolumeExporter::getConferenceDate('2026-02-30', '2026-03-03'), 'Not rolled over to March 2');
        self::assertNull(CrossrefVolumeExporter::getConferenceDate('2026-03-01', '2026-04-31'), 'Not rolled over to May 1');
        self::assertNull(CrossrefVolumeExporter::getConferenceDate('1399-12-31', '2026-03-01'), 'Year refused by Crossref');
        self::assertNull(CrossrefVolumeExporter::getConferenceDate('2026-03-01', '2201-01-01'), 'Year refused by Crossref');
    }

    public function testEditorsAreDepositedAsContributors(): void
    {
        $first = new Episciences_Editor();
        $first->setFirstname('Ada');
        $first->setLastname('Lovelace');
        $first->setOrcid('0000-0002-1825-009x');
        $second = new Episciences_Editor();
        $second->setLastname('Turing');
        $withoutName = new Episciences_Editor();

        $volume = $this->makeVolume();
        $volume->setEditors([1 => $first, 2 => $withoutName, 3 => $second]);

        $xpath = $this->xpath($this->makeExporter()->export($volume, $this->makeJournal()));
        $names = $xpath->query('/c:doi_batch/c:body/c:conference/c:contributors/c:person_name');
        $first = $names->item(0);
        $last = $names->item(1);

        self::assertSame(2, $names->length);
        self::assertInstanceOf(DOMElement::class, $first);
        self::assertInstanceOf(DOMElement::class, $last);
        self::assertSame('first', $first->getAttribute('sequence'));
        self::assertSame('editor', $first->getAttribute('contributor_role'));
        self::assertSame('https://orcid.org/0000-0002-1825-009X', $this->text($xpath, '//c:person_name[1]/c:ORCID'));
        self::assertSame('additional', $last->getAttribute('sequence'));
        self::assertNull($this->text($xpath, '//c:person_name[2]/c:given_name'));
        self::assertSame('event_metadata', $xpath->query('//c:contributors/following-sibling::*[1]')->item(0)->localName);
    }

    public function testRefusesAVolumeThatIsNotAProceedings(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeExporter()->export(
            $this->makeVolume([Episciences_Volume::VOLUME_IS_PROCEEDING => '0']),
            $this->makeJournal()
        );
    }

    /**
     * @dataProvider missingDoiProvider
     */
    public function testRefusesAProceedingsWithoutDoi(string $doi): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeExporter()->export(
            $this->makeVolume([Episciences_Volume::VOLUME_CONFERENCE_DOI => $doi]),
            $this->makeJournal()
        );
    }

    /**
     * @dataProvider missingRequiredMetadataProvider
     * @param array<string, string> $settings
     * @param array<string, string>|null $titles
     */
    public function testRefusesAProceedingsWithoutRequiredMetadata(array $settings, ?array $titles): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeExporter()->export($this->makeVolume($settings, $titles), $this->makeJournal());
    }

    /**
     * @return array<string, array{array<string, string>, array<string, string>|null}>
     */
    public static function missingRequiredMetadataProvider(): array
    {
        return [
            'no conference name' => [[Episciences_Volume::VOLUME_CONFERENCE_NAME => '  '], null],
            'no title' => [[], ['en' => ' ']],
        ];
    }

    public function testAcceptsASuffixEndingWithTheInfix(): void
    {
        $doi = '10.46298/mbj.proceedings.a.proceedings.';

        self::assertSame($doi, CrossrefVolumeExporter::getDepositableDoi(
            $this->makeVolume([Episciences_Volume::VOLUME_CONFERENCE_DOI => $doi])
        ));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingDoiProvider(): array
    {
        return [
            'empty' => [''],
            'prefix only' => ['10.46298/mbj.proceedings.'],
        ];
    }

    public function testValuesAreEscaped(): void
    {
        $volume = $this->makeVolume([Episciences_Volume::VOLUME_CONFERENCE_NAME => 'Tom & Jerry <Conf>']);
        $xpath = $this->xpath($this->makeExporter()->export($volume, $this->makeJournal()));

        self::assertSame('Tom & Jerry <Conf>', $this->text($xpath, '//c:conference_name'));
    }
}

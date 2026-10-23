<?php
declare(strict_types=1);

namespace unit\scripts;

use JsonException;
use NormalizeUserAffiliationsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/NormalizeUserAffiliationsCommand.php';

/**
 * Unit tests for NormalizeUserAffiliationsCommand.
 *
 * These tests guard the one-off migration that repairs USER.affiliations rows
 * saved as raw "Label #rorId" strings by the account-creation bug (see
 * UserDefaultController::createAction()) instead of {label, rorId} objects.
 * All tests are pure: no bootstrap-time side effects, no database, no I/O.
 */
class NormalizeUserAffiliationsCommandTest extends TestCase
{
    private NormalizeUserAffiliationsCommand $command;

    protected function setUp(): void
    {
        $this->command = new NormalizeUserAffiliationsCommand();
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('users:normalize-affiliations', $this->command->getName());
    }

    public function testCommandHasUidOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('uid'));
        $this->assertTrue($definition->getOption('uid')->isValueRequired(), '--uid must require a value');
    }

    public function testCommandHasBufferOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('buffer'));
        $this->assertTrue($definition->getOption('buffer')->isValueRequired(), '--buffer must require a value');
    }

    public function testCommandHasDryRunFlag(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), '--dry-run must be a flag');
    }

    public function testBufferOptionDefaultIs500(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertSame(
            NormalizeUserAffiliationsCommand::DEFAULT_BUFFER,
            $definition->getOption('buffer')->getDefault()
        );
    }

    // -------------------------------------------------------------------------
    // validateBuffer()
    // -------------------------------------------------------------------------

    public function testValidateBuffer_positiveValue_returnedAsIs(): void
    {
        $this->assertSame(100, $this->command->validateBuffer(100));
    }

    public function testValidateBuffer_zero_returnsDefault(): void
    {
        $this->assertSame(NormalizeUserAffiliationsCommand::DEFAULT_BUFFER, $this->command->validateBuffer(0));
    }

    public function testValidateBuffer_negative_returnsDefault(): void
    {
        $this->assertSame(NormalizeUserAffiliationsCommand::DEFAULT_BUFFER, $this->command->validateBuffer(-1));
    }

    public function testValidateBuffer_null_returnsDefault(): void
    {
        $this->assertSame(NormalizeUserAffiliationsCommand::DEFAULT_BUFFER, $this->command->validateBuffer(null));
    }

    // -------------------------------------------------------------------------
    // disassembleAffiliationEntry()
    // -------------------------------------------------------------------------

    public function testDisassemble_labelAndValidRorId(): void
    {
        $result = $this->command->disassembleAffiliationEntry('Donders Centre for Cognitive Neuroimaging [DCCN] #https://ror.org/01jdz5g73');

        $this->assertSame([
            'label' => 'Donders Centre for Cognitive Neuroimaging [DCCN]',
            'rorId' => 'https://ror.org/01jdz5g73',
        ], $result);
    }

    public function testDisassemble_trimsWhitespaceAroundSeparator(): void
    {
        $result = $this->command->disassembleAffiliationEntry('Radboud University Nijmegen  #https://ror.org/016xsfp80');

        $this->assertSame('Radboud University Nijmegen', $result['label']);
        $this->assertSame('https://ror.org/016xsfp80', $result['rorId']);
    }

    public function testDisassemble_invalidRorIdIsDropped(): void
    {
        $result = $this->command->disassembleAffiliationEntry('Some Lab #not-a-ror-id');

        $this->assertSame('Some Lab', $result['label']);
        $this->assertSame('', $result['rorId']);
    }

    public function testDisassemble_labelWithoutSeparator(): void
    {
        $result = $this->command->disassembleAffiliationEntry('Just A Label');

        $this->assertSame(['label' => 'Just A Label', 'rorId' => ''], $result);
    }

    public function testDisassemble_emptyString(): void
    {
        $this->assertSame(['label' => '', 'rorId' => ''], $this->command->disassembleAffiliationEntry(''));
    }

    public function testDisassemble_alreadyCorrectArrayIsUntouched(): void
    {
        $value = ['label' => 'Already Correct', 'rorId' => 'https://ror.org/01jdz5g73'];

        $this->assertSame($value, $this->command->disassembleAffiliationEntry($value));
    }

    public function testDisassemble_arrayMissingKeysFallsBackToEmpty(): void
    {
        $this->assertSame(
            ['label' => '', 'rorId' => ''],
            $this->command->disassembleAffiliationEntry(['foo' => 'bar'])
        );
    }

    // -------------------------------------------------------------------------
    // normalizeAffiliationsJson()
    // -------------------------------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testNormalize_singleLegacyStringEntry(): void
    {
        $json = '{"affiliations":["IT University of Copenhagen  #https:\/\/ror.org\/02309jg23"]}';

        $result = $this->command->normalizeAffiliationsJson($json);

        $this->assertNotNull($result);
        $decoded = json_decode((string) $result, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([
            ['label' => 'IT University of Copenhagen', 'rorId' => 'https://ror.org/02309jg23'],
        ], $decoded['affiliations']);
    }

    /**
     * @throws JsonException
     */
    public function testNormalize_multipleLegacyStringEntries(): void
    {
        $json = '{"affiliations":["University of Gothenburg  #https:\/\/ror.org\/01tm6cn81","Chalmers University of Technology  #https:\/\/ror.org\/040wg7k59"]}';

        $result = $this->command->normalizeAffiliationsJson($json);
        $decoded = json_decode((string) $result, true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(2, $decoded['affiliations']);
        $this->assertSame('University of Gothenburg', $decoded['affiliations'][0]['label']);
        $this->assertSame('Chalmers University of Technology', $decoded['affiliations'][1]['label']);
    }

    /**
     * @throws JsonException
     */
    public function testNormalize_preservesOtherProfileKeys(): void
    {
        $json = '{"webSites":["https:\/\/example.org\/"],"affiliations":["Some Lab #https:\/\/ror.org\/01jdz5g73"]}';

        $result = $this->command->normalizeAffiliationsJson($json);
        $decoded = json_decode((string) $result, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['https://example.org/'], $decoded['webSites']);
    }

    /**
     * @throws JsonException
     */
    public function testNormalize_emptyStringEntryBecomesEmptyObject(): void
    {
        $json = '{"affiliations":[""]}';

        $result = $this->command->normalizeAffiliationsJson($json);
        $decoded = json_decode((string) $result, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([['label' => '', 'rorId' => '']], $decoded['affiliations']);
    }

    /**
     * @throws JsonException
     */
    public function testNormalize_alreadyCorrectFormatReturnsNull(): void
    {
        $json = '{"affiliations":[{"label":"Donders Centre","rorId":"https:\/\/ror.org\/01jdz5g73"}]}';

        $this->assertNull($this->command->normalizeAffiliationsJson($json));
    }

    /**
     * @throws JsonException
     */
    public function testNormalize_noAffiliationsKeyReturnsNull(): void
    {
        $this->assertNull($this->command->normalizeAffiliationsJson('{"webSites":["https:\/\/example.org\/"]}'));
    }

    /**
     * @throws JsonException
     */
    public function testNormalize_emptyAffiliationsArrayReturnsNull(): void
    {
        $this->assertNull($this->command->normalizeAffiliationsJson('{"affiliations":[]}'));
    }

    public function testNormalize_emptyStringInputReturnsNull(): void
    {
        $this->assertNull($this->command->normalizeAffiliationsJson(''));
    }

    public function testNormalize_invalidJsonThrows(): void
    {
        $this->expectException(JsonException::class);
        $this->command->normalizeAffiliationsJson('{not-json');
    }

    /**
     * @throws JsonException
     */
    public function testNormalize_mixedLegacyAndCorrectEntriesNormalizesAll(): void
    {
        $json = '{"affiliations":['
            . '{"label":"Already Correct","rorId":"https:\/\/ror.org\/01jdz5g73"},'
            . '"Legacy Lab #https:\/\/ror.org\/016xsfp80"'
            . ']}';

        $result = $this->command->normalizeAffiliationsJson($json);
        $decoded = json_decode((string) $result, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Already Correct', $decoded['affiliations'][0]['label']);
        $this->assertSame('Legacy Lab', $decoded['affiliations'][1]['label']);
    }

    /**
     * @throws JsonException
     * Idempotency: running the migration twice on already-fixed data must be a no-op.
     */
    public function testNormalize_isIdempotent(): void
    {
        $json = '{"affiliations":["Some Lab #https:\/\/ror.org\/01jdz5g73"]}';

        $firstPass = $this->command->normalizeAffiliationsJson($json);
        $this->assertNotNull($firstPass);

        $secondPass = $this->command->normalizeAffiliationsJson((string) $firstPass);
        $this->assertNull($secondPass, 'A second pass over already-normalized data must report no change');
    }
}

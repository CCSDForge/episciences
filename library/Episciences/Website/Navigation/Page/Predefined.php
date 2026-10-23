<?php

/**
 * Base class for all predefined (static) navigation pages.
 *
 * A predefined page is a page whose permalink is fixed and whose content
 * is managed through the T_PAGES table. It is distinguished from custom
 * pages (user-defined slug) and functional pages (browse, search, etc.).
 *
 * PERMALINK DERIVATION
 * --------------------
 * The permalink is derived automatically from the class short name using a
 * CamelCase → kebab-case conversion:
 *   EditorialBoard   → editorial-board
 *   ScientificAdvisoryBoard → scientific-advisory-board
 *
 * Subclasses therefore need no property declaration. Override $OVERRIDE_PERMALIEN
 * only when the generated slug must differ from the class-name-derived one.
 *
 *
 * HOW TO ADD A NEW PREDEFINED PAGE
 * ---------------------------------
 * 1. Create the page class file:
 *    library/Episciences/Website/Navigation/Page/MyNewPage.php
 *
 *      <?php
 *      class Episciences_Website_Navigation_Page_MyNewPage extends Episciences_Website_Navigation_Page_Predefined
 *      {
 *      }
 *
 *    The permalink is derived automatically: MyNewPage → my-new-page.
 *    Only add a protected string $OVERRIDE_PERMALIEN = 'custom-slug' if the
 *    desired URL slug differs from the CamelCase conversion.
 *
 * 2. Add a PAGE_ constant in Episciences_Website_Navigation (Navigation.php):
 *
 *      const PAGE_MY_NEW_PAGE = 'myNewPage';
 *
 *    The value must be the class short name with its first letter lowercased.
 *    getPageClass() calls ucfirst() on it to reconstruct the class name.
 *
 * 3. Register the class short name in PREDEFINED_TYPES below.
 *
 * 4. Add translation entries in application/languages/{en,fr}/views.php:
 *
 *      'Episciences_Website_Navigation_Page_MyNewPage' => 'My new page',
 *
 * That is all. No permalink property, no ReflectionClass, no filesystem scan.
 */
class Episciences_Website_Navigation_Page_Predefined extends Episciences_Website_Navigation_Page
{
    /**
     * Short names of all registered predefined page subclasses.
     * A short name is the segment after the last underscore in the full class name.
     *
     * Add the short name here whenever a new predefined page class is created (step 3 above).
     *
     * @var list<string>
     */
    private const PREDEFINED_TYPES = [
        'About',
        'Credits',
        'EditorialBoard',
        'EditorialWorkflow',
        'EthicalCharter',
        'ForConferenceOrganisers',
        'ForEditors',
        'FormerMembers',
        'ForReviewers',
        'IntroductionBoard',
        'JournalAcknowledgements',
        'JournalIndexing',
        'OperatingCharterBoard',
        'PrepareSubmission',
        'ProposingSpecialIssues',
        'PublishingPolicies',
        'ReviewersBoard',
        'ScientificAdvisoryBoard',
        'TechnicalBoard',
    ];

    const PERMALIEN = 'permalien';

    protected $_controller = 'page';
    protected $_multiple = false;

    /**
     * Leave empty to use the auto-derived permalink (CamelCase → kebab-case).
     * Set explicitly only when the desired slug cannot be derived from the class name.
     */
    protected string $_permalien = '';

    protected string $_page = '';

    /** @var array<string, string>|null */
    private static ?array $_cachedPermaliens = null;


    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $array = parent::toArray();
        $array[self::PERMALIEN] = $this->getPermalien();
        return $array;
    }


    /**
     * Returns the permalink for this page.
     *
     * Uses $_permalien when set explicitly (e.g. loaded from DB or overridden in a
     * subclass). Otherwise derives the slug from the concrete class short name via
     * CamelCase → kebab-case conversion.
     */
    public function getPermalien(): string
    {
        if ($this->_permalien !== '') {
            return $this->_permalien;
        }
        return self::derivePermalien(static::class);
    }


    public function setPermalien(string $permalien): void
    {
        $this->_permalien = $permalien;
    }


    public function getAction(): string
    {
        return $this->getPermalien();
    }


    /** @param mixed $pageidx */
    public function getForm(mixed $pageidx)
    {
        parent::getForm($pageidx);
        if (!$this->_form->getElement(self::PERMALIEN)) {
            $this->_form->addElement('hidden', self::PERMALIEN, [
                'required'  => true,
                'value'     => $this->getPermalien(),
                'belongsTo' => 'pages_' . $pageidx,
                'class'     => 'permalien',
            ]);
        }
        $this->_form->getElement('labels')->setOptions(['class' => 'inputlangmulti permalien-src']);
        return $this->_form;
    }


    /** @param array<string, mixed> $options */
    public function setOptions($options = []): void
    {
        foreach ($options as $option => $value) {
            $key = strtolower($option);
            if ($key === self::PERMALIEN) {
                $this->setPermalien($value);
            } elseif ($key === 'page') {
                $this->_page = $value;
            }
        }

        parent::setOptions($options);
    }


    public function getSuppParams(): string
    {
        if ($this->_permalien === '') {
            return '';
        }
        return serialize([self::PERMALIEN => $this->_permalien]);
    }


    /**
     * Returns all predefined page permalinks, keyed by fully-qualified class name.
     *
     * Uses PREDEFINED_TYPES — no filesystem scan, no ReflectionClass.
     * Result is cached for the lifetime of the request.
     *
     * @return array<string, string>  e.g. ['Episciences_Website_Navigation_Page_About' => 'about', ...]
     */
    public static function getAllPermaliens(): array
    {
        if (self::$_cachedPermaliens === null) {
            self::$_cachedPermaliens = [];
            foreach (self::PREDEFINED_TYPES as $shortName) {
                $class = 'Episciences_Website_Navigation_Page_' . $shortName;
                self::$_cachedPermaliens[$class] = self::derivePermalien($class);
            }
        }
        return self::$_cachedPermaliens;
    }


    /**
     * Returns true if $pageCode is the permalink of any registered predefined page.
     */
    public static function isPredefinedPage(string $pageCode): bool
    {
        return in_array($pageCode, self::getAllPermaliens(), true);
    }


    /**
     * Converts a fully-qualified or short class name to its kebab-case permalink.
     *
     * Only the segment after the last underscore is used:
     *   "Episciences_Website_Navigation_Page_EditorialBoard" → "editorial-board"
     *   "EditorialBoard"                                     → "editorial-board"
     */
    private static function derivePermalien(string $className): string
    {
        $shortName = substr($className, strrpos($className, '_') + 1);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $shortName));
    }
}

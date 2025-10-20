<?php

class Episciences_Rating_Criterion
{
    const TYPE_SEPARATOR = 'separator';
    const TYPE_CRITERION = 'criterion';

    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_CONTRIBUTOR = 'contributor';
    const VISIBILITY_EDITORS = 'editors';
    public static $visibilityEmojis = [
        self::VISIBILITY_PUBLIC => '🌐',
        self::VISIBILITY_CONTRIBUTOR => '✏️',
        self::VISIBILITY_EDITORS => '👥'
    ];

    const EVALUATION_TYPE_FREE = 'free';
    const EVALUATION_TYPE_QUANTITATIVE = 'quantitative';
    const EVALUATION_TYPE_QUALITATIVE = 'qualitative';
    const EVALUATION_TYPE_QUANTITATIVE_CUSTOM = 'quantitative_custom';
    const EVALUATION_TYPE_QUALITATIVE_CUSTOM = 'qualitative_custom';

    private $_id;
    private $_labels = [];
    private $_descriptions = [];
    // type: separator or criterion
    private $_type;

    /**
     * @var string
     */
    private $_subType ='';


    // does the grid allow the reviewer to comment ? (true or false)
    private $_comment_setting;
    // reviewer comment (if canComment is true)
    private $_comment;
    // does the grid allow the reviewer to upload a file ? (true or false)
    private $_attachment_setting;
    // uploaded file (if canUpload is true)
    private $_attachment;
    // criterion coefficient (quantitative rating only)
    private $_coefficient;


    /**
     * public, contributor, or editors
     * @var string
     */
    private $_visibility;
    // criterion position in the grid
    private $_position;
    // if rating is quantitative or qualitative, reviewer has to pick one of these values (select)
    private $_options = [];
    // reviewer note (selected from the options array)
    private $_note;

    public function __construct($values = [])
    {
        if (is_array($values)) {
            $this->populate($values);
        }
    }

    public function populate($values)
    {
        foreach ($values as $name => $value) {
            $method = 'set' . ucfirst(strtolower($name));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    public function getId()
    {
        return $this->_id;
    }

    public function setId($id)
    {
        $this->_id = $id;
    }

    public function getLabel($lang = null)
    {
        $labels = $this->_labels;
        $label = null;

        if (!$lang) {
            $lang = Episciences_Tools::getLocale();
        }

        if ($lang && array_key_exists($lang, $labels)) {
            $label = $labels[$lang];
        } elseif (array_key_exists('en', $labels)) {
            $label = $labels['en'];
        } elseif (!empty($labels)) {
            $label = reset($labels);
        }

        return $label;
    }

    public function setLabel($locale, $value)
    {
        $this->_labels[$locale] = $value;
    }

    /**
     * fetch criterion description
     * @param null $lang
     * @return mixed|null
     */
    public function getDescription($lang = null)
    {
        $descriptions = $this->_descriptions;
        $description = null;

        if (!$lang) {
            $lang = Episciences_Tools::getLocale();
        }

        if ($lang && array_key_exists($lang, $descriptions)) {
            $description = $descriptions[$lang];
        } elseif (array_key_exists('en', $descriptions)) {
            $description = $descriptions['en'];
        } elseif (!empty($descriptions)) {
            $description = reset($descriptions);
        }

        return $description;
    }

    public function setDescription($locale, $value)
    {
        $this->_descriptions[$locale] = $value;
    }

    public function getOptionLabel($note, $lang = null)
    {
        // check if option exists
        $option = $this->getOption($note);
        if (!$option) {
            return null;
        }

        // check if option has labels
        $labels = $option['label'];
        if (!is_array($labels)) {
            return null;
        }

        $label = null;

        // select appropriate label lang
        if (!$lang) {
            $lang = Episciences_Tools::getLocale();
        }

        if ($lang && array_key_exists($lang, $labels)) {
            $label = $labels[$lang];
        } elseif (array_key_exists('en', $labels)) {
            $label = $labels['en'];
        } elseif (!empty($labels)) {
            $label = reset($labels);
        }

        return $label;
    }

    public function getOption($id)
    {
        return (array_key_exists($id, $this->getOptions())) ? $this->_options[$id] : false;
    }

    public function getOptions()
    {
        return $this->_options;
    }

    public function setOptions($options)
    {
        $this->_options = $options;
    }

    public function getMaxNote()
    {
        $max = max(array_keys($this->getOptions()));
        return $max > 0 ? $max : 1;
    }

    public function hasCoefficient()
    {
        return (is_numeric($this->getCoefficient()));
    }

    public function getCoefficient()
    {
        return $this->_coefficient;
    }

    public function setCoefficient($coefficient)
    {
        $this->_coefficient = $coefficient;
    }

    public function allowsComment()
    {
        return ($this->_comment_setting);
    }

    public function allowsAttachment()
    {
        return ($this->_attachment_setting);
    }

    public function allowsNote()
    {
        return ($this->hasOptions());
    }

    public function hasOptions()
    {
        return (!empty($this->_options));
    }

    /**
     * return true if criterion has custom labels, false otherwise
     * @return bool
     */
    public function isCustom()
    {
        $custom = false;
        foreach ($this->getOptions() as $option) {
            if (array_key_exists('label', $option) && !empty($option['label'])) {
                $custom = true;
                break;
            }
        }
        return $custom;
    }

    /**
     * return true if criterion is empty (no value of any kind), false otherwise
     * @return bool
     */
    public function isEmpty()
    {
        return (!$this->hasNote() && !$this->hasComment() && !$this->hasAttachment());
    }

    /**
     * return true if criterion has a note, false otherwise
     * @return bool
     */
    public function hasNote()
    {
        return (is_numeric($this->getNote()));
    }

    public function getNote()
    {
        return $this->_note;
    }

    public function setNote($note)
    {
        $this->_note = (int)$note;
    }

    /**
     * return true if criterion has a comment, false otherwise
     * @return bool
     */
    public function hasComment()
    {
        return (!empty($this->getComment()));
    }

    public function getComment()
    {
        return $this->_comment;
    }

    public function setComment($comment)
    {
        $this->_comment = $comment;
    }

    public function hasAttachment()
    {
        return ($this->getAttachment() != null);
    }

    public function getAttachment()
    {
        return $this->_attachment;
    }

    public function setAttachment($attachment)
    {
        $this->_attachment = $attachment;
    }

    /**
     * return true if criterion is a separator, false otherwise
     * @return bool
     */
    public function isSeparator()
    {
        return ($this->getType() == self::TYPE_SEPARATOR);
    }

    public function getType()
    {
        return $this->_type;
    }

    public function setType($type)
    {
        $this->_type = $type;
    }

    // return attached file path

    /**
     * return true if criterion is a criterion (not a separator), false otherwise
     * @return bool
     */
    public function isCriterion()
    {
        return ($this->getType() == self::TYPE_CRITERION);
    }

    // set attached file path

    /**
     * return true if criterion has a value of any kind, false otherwise
     * @return bool
     */
    public function hasValue()
    {
        return ($this->hasNote() || $this->hasComment() || $this->hasAttachment());
    }

    public function toArray()
    {
        $array = [
            'type' => $this->getType(),
            'labels' => $this->getLabels(),
            'descriptions' => $this->getDescriptions(),
            'visibility' => $this->getVisibility(),
            'position' => $this->getPosition()
        ];

        if ($this->getType() == self::TYPE_CRITERION) {
            $array = array_merge($array, [
                'options' => $this->getOptions(),
                'note' => $this->getNote(),
                'coefficient' => $this->getCoefficient(),
                'comment' => $this->getComment(),
                'attachment' => $this->getAttachment(),
                'comment_setting' => $this->getComment_setting(),
                'attachment_setting' => $this->getAttachment_setting()
            ]);
        }

        return $array;
    }

    public function getLabels()
    {
        return $this->_labels;
    }

    // return true if criterion allows attachment, false otherwise

    public function setLabels($labels)
    {
        $this->_labels = $labels;
    }

    // return true if criterion allows a note, false otherwise

    public function getDescriptions()
    {
        return $this->_descriptions;
    }

    // return true if criterion has an attachment, false otherwise

    public function setDescriptions($descriptions)
    {
        $this->_descriptions = $descriptions;
    }

    /**
     * @return string
     */
    public function getVisibility(): string
    {
        return $this->_visibility;
    }

    /**
     * @param string $visibility
     */
    public function setVisibility(string $visibility)
    {
        $this->_visibility = $visibility;
    }

    public function getPosition()
    {
        return $this->_position;
    }

    public function setPosition($position)
    {
        $this->_position = $position;
    }

    public function getComment_setting()
    {
        return $this->_comment_setting;
    }

    public function setComment_setting($bool)
    {
        $this->_comment_setting = $bool;
    }

    public function getAttachment_setting()
    {
        return $this->_attachment_setting;
    }

    public function setAttachment_setting($bool)
    {
        $this->_attachment_setting = $bool;
    }

    /**
     * @return string
     */
    public function getSubType(): string
    {
        return $this->_subType;
    }

    /**
     * @param string $subType
     */
    public function setSubType(string $subType)
    {
        $this->_subType = $subType;
    }


}

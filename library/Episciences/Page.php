<?php

class Episciences_Page
{

    private int $id;
    private string $code;
    private int $uid;
    private string $date_creation;
    private string $date_updated;
    private string $title;
    private string $content;
    private string $visibility;
    private string $page_code;


    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    public function setOptions(array $options): void
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = strtolower($key);
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods)) {
                $this->$method($value);
            }
        }
    }


    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return int
     */
    public function getUid(): int
    {
        return $this->uid;
    }

    /**
     * @param int $uid
     */
    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    /**
     * @return string
     */
    public function getDateCreation(): string
    {
        return $this->date_creation;
    }

    /**
     * @param string $date_creation
     */
    public function setDateCreation(string $date_creation): void
    {
        $this->date_creation = $date_creation;
    }

    /**
     * @return string
     */
    public function getDateUpdated(): string
    {
        return $this->date_updated;
    }

    /**
     * @param string $date_updated
     */
    public function setDateUpdated(string $date_updated): void
    {
        $this->date_updated = $date_updated;
    }


    public function getTitle(bool $serialized = false): string
    {
        if ($serialized) {
            $this->title = json_encode($this->title, JSON_UNESCAPED_UNICODE);
        }
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }


    public function getContent(bool $serialized = false): string
    {
        if ($serialized) {
            $this->content = json_encode($this->content, JSON_UNESCAPED_UNICODE);
        }
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }


    public function getVisibility(bool $serialized = false): string
    {
        if ($serialized) {
            $this->visibility = json_encode($this->visibility, JSON_UNESCAPED_UNICODE);
        }
        return $this->visibility;
    }

    /**
     * @param string $visibility
     */
    public function setVisibility(string $visibility): void
    {
        $this->visibility = $visibility;
    }

    /**
     * @return string
     */
    public function getPageCode(): string
    {
        return $this->page_code;
    }

    /**
     * @param string $page_code
     */
    public function setPageCode(string $page_code): void
    {
        $this->page_code = $page_code;
    }


}
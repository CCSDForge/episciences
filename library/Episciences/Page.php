<?php

use League\HTMLToMarkdown\HtmlConverter;

class Episciences_Page
{
    private int $id = 0;
    private string $code = '';
    private int $uid = 0;
    private string $date_creation = '';
    private string $date_updated = '';
    private string|array $title = '';
    private string|array $content = '';
    private string|array $visibility = '';
    private string $page_code = '';

    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    public function setOptions(array $options): void
    {
        foreach ($options as $key => $value) {
            // Convert snake_case to camelCase (e.g., page_code -> PageCode)
            $camelKey = str_replace('_', '', ucwords($key, '_'));
            $method = 'set' . $camelKey;
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function getDateCreation(): string
    {
        return $this->date_creation;
    }

    public function setDateCreation(string $date_creation = ''): void
    {
        $this->date_creation = $date_creation;
    }

    public function getDateUpdated(): string
    {
        return $this->date_updated;
    }

    public function setDateUpdated(string $date_updated): void
    {
        $this->date_updated = $date_updated;
    }

    public function getTitle(bool $deserialize = false): string|array
    {
        return $deserialize && is_string($this->title) ? json_decode($this->title, true) : $this->title;
    }

    public function setTitle(string|array $title, bool $serialize = true): void
    {
        $this->title = $serialize && is_array($title) ? json_encode($title, JSON_UNESCAPED_UNICODE) : $title;
    }

    public function getContent(bool $deserialize = false): string|array
    {
        return $deserialize && is_string($this->content) ? json_decode($this->content, true) : $this->content;
    }

    public function setContent(string|array $content, bool $serialize = true): void
    {
        $converter = new HtmlConverter(array('strip_tags' => true, 'header_style' => 'atx'));
        if (is_array($content)) {
            foreach ($content as $language => $value) {
                $content[$language] = $converter->convert($value);
            }
        }
        $this->content = $serialize && is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : $content;
    }

    public function getVisibility(bool $deserialize = false): string|array
    {
        return $deserialize && is_string($this->visibility) ? json_decode($this->visibility, true) : $this->visibility;
    }

    public function setVisibility(string|array $visibility, bool $serialize = true): void
    {
        $this->visibility = $serialize && is_array($visibility) ? json_encode($visibility, JSON_UNESCAPED_UNICODE) : $visibility;
    }

    public function getPageCode(): string
    {
        return $this->page_code;
    }

    public function setPageCode(string $page_code): void
    {
        $this->page_code = $page_code;
    }
}

<?php

namespace Episciences\Paper;

use JsonSerializable;

class DocumentConfiguration implements JsonSerializable
{
    public const MAIN_PDF_URL_PATH = '$.document.database.mainPdfUrl';
    private int $docId;
    private array $data;


    public function __construct(int $docId, array $data = []){
        $this->docId = $docId;
        $this->data = $data;
    }

    /**
     * Récupérer une valeur imbriquée via notation pointée
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $keys = explode('.', $path);
        $value = $this->data;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Définir une valeur imbriquée
     */
    public function set(string $path, mixed $value): self
    {
        $keys = explode('.', $path);
        $reference = &$this->data;

        foreach ($keys as $index => $key) {
            if ($index === count($keys) - 1) {
                $reference[$key] = $value;
            } else {
                if (!isset($reference[$key]) || !is_array($reference[$key])) {
                    $reference[$key] = [];
                }
                $reference = &$reference[$key];
            }
        }

        unset($reference);
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'docId' => $this->docId,
            'data' => $this->data,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }


}
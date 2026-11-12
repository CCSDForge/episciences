<?php

declare(strict_types=1);

namespace Episciences\View\Helper;

use Episciences\Reviewer\RequestType;
use Episciences\Reviewer\RequestTypeRepository;
use Episciences_Tools;
use Zend_View_Helper_Abstract;

/**
 * Label of a review request type (#319), HTML-escaped, in the language of the page; empty for an unknown type.
 * The types of the journal are read once per request.
 */
class RequestTypeLabel extends Zend_View_Helper_Abstract
{
    /** @var array<int, array<int, RequestType>> types by id, by journal */
    private array $types = [];

    private ?RequestTypeRepository $repository;

    public function __construct(?RequestTypeRepository $repository = null)
    {
        $this->repository = $repository;
    }

    public function requestTypeLabel(int|string|null $requestTypeId, int $rvid = RVID, ?string $locale = null): string
    {
        if ($requestTypeId === null || $requestTypeId === '') {
            return '';
        }

        if (!isset($this->types[$rvid])) {
            $this->types[$rvid] = [];
            foreach (($this->repository ??= new RequestTypeRepository())->findAllForJournal($rvid) as $type) {
                $this->types[$rvid][(int)$type->getId()] = $type;
            }
        }

        $type = $this->types[$rvid][(int)$requestTypeId] ?? null;

        return $type === null ? '' : $this->view->escape($type->getLabel($locale ?? Episciences_Tools::getLocale()));
    }
}

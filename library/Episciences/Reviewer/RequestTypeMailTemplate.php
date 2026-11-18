<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences_Mail_Template;
use Episciences_Mail_TemplatesManager;
use LogicException;
use Zend_Db_Adapter_Exception;
use Zend_Exception;

/**
 * Invitation template of a review request type created by a journal (#319).
 *
 * The default types have their own default template. A type created by a journal gets, at its creation, a custom
 * template of the journal (paper_reviewer_invitation_type_<id>) copied from the generic one, which the journal can
 * then edit in the mail templates screen like any other template.
 */
final class RequestTypeMailTemplate
{
    /**
     * @throws LogicException for a type not saved yet: the key of a type created by a journal holds its id
     */
    public static function key(RequestType $type): string
    {
        $suffix = $type->invitationKeySuffix() ?? throw new LogicException('The review request type has no id yet');

        return Episciences_Mail_TemplatesManager::INVITATION_KEY_PREFIX . $suffix;
    }

    /**
     * @return bool false when the template could not be created
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     */
    public static function createFor(RequestType $type, string $rvcode): bool
    {
        if ($type->isDefault() || self::find($type, $rvcode) !== null) {
            return true;
        }

        $generic = new Episciences_Mail_Template();
        $generic->setRvcode($rvcode);

        if (!$generic->findByKey(Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_GENERIC)) {
            return false;
        }

        $generic->loadTranslations();

        $names = [];
        foreach ($type->getLabels() as $lang => $label) {
            $names[$lang] = 'Invitation - ' . $label;
        }

        $template = new Episciences_Mail_Template();
        $template
            ->setRvcode($rvcode)
            ->setRvid($type->getRvid())
            // the default generic template is the parent of the new custom template
            ->setId($generic->getParentid() ?: $generic->getId())
            ->setKey(self::key($type))
            ->setType($generic->getType())
            ->setName($names)
            ->setSubject($generic->getSubjectTranslations())
            ->setBody($generic->getBodyTranslations());

        return $template->save();
    }

    /**
     * Deletes the template of a type created by the journal (the type itself has been deleted)
     */
    public static function deleteFor(RequestType $type, string $rvcode): bool
    {
        if ($type->isDefault()) {
            return true;
        }

        $template = self::find($type, $rvcode);

        return $template === null || !$template->isCustom() || $template->delete();
    }

    private static function find(RequestType $type, string $rvcode): ?Episciences_Mail_Template
    {
        $template = new Episciences_Mail_Template();
        $template->setRvcode($rvcode);

        return $template->findByKey(self::key($type)) ? $template : null;
    }
}

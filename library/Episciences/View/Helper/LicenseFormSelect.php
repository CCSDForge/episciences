<?php

use Episciences\Paper\Spdx\LicenseManager;


/**
 * View Helper pour générer un select de la liste de licences
 * Utilisation :
 *
 * Simple
 * LicensesForm('license')
 *
 * Avec valeur pré-sélectionnée
 * LicensesForm('license', 'MIT')
 *
 * Avec attributs personnalisés
 * licensesForm('license', 'Apache-2.0', array(
 * 'class' => 'form-control',
 * 'data-license-family' => 'permissive'
 * ));
 *
 */
class Episciences_View_Helper_LicenseFormSelect extends Zend_View_Helper_FormSelect
{
    public const EMPTY_LABEL = '--- Sélectionner une licence ---';

    public function licenseFormSelect($name, $value = null, $attribs = []): string
    {
        $licenses = $this->buildForm();

        if (!isset($attribs['id'])) {
            $attribs['id'] = $name;
        }

        $attribs['class'] = $attribs['class'] ?? sprintf('%s-select', $name);

        return $this->view->formSelect($name, $value, $attribs, $licenses);
    }

    protected function buildForm(): ?array
    {
        $licenses = LicenseManager::fetchRecommended();
        $options = ['' => $this->view->translate(self::EMPTY_LABEL)];
        /** @var \Episciences\Paper\Spdx\LicenseCode$license */
        foreach ($licenses as $license) {
            $code = $license->getCode();
            $options[$code] = sprintf('%s (%s)', $this->view->translate($code), $code);
        }

        ksort($options);
        return $options;
    }
}


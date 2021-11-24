<?php

/**
 * Class Ccsd_View_Helper_Navbar
 */
class Ccsd_View_Helper_Navbar extends Zend_View_Helper_Abstract
{
    /**
     * UI languages display
     *
     * @var bool
     */
    protected $_displayLang = false;

    /**
     * Array Of UI languages
     *
     * @var array
     */
    protected $_languages = [];

    /**
     * @var string
     */
    protected $_lang = '';

    /**
     * @var bool
     */
    protected $_displayLogin = false;

    /**
     * @var bool
     */
    protected $_isLogged = false;

    /**
     * @var string
     */
    protected $_userRenderScript = 'commons/user.phtml';

    /**
     * @var string
     */
    protected $_navBarSearchScript = 'commons/navbar-search-form.phtml';

    /**
     * @var bool
     */
    protected $_labelEnv = false;

    /**
     * @var string
     */
    protected $_labelEnvClass = '';

    /**
     * @param bool $displayLang
     * @param bool $langOptions
     * @param bool $displayLogin
     * @param array $loginOptions
     */
    public function navbar($displayLang, $langOptions, $displayLogin, $loginOptions)
    {
        if ($displayLang) {
            $this->_displayLang = true;
            foreach ([
                         'languages',
                         'lang'
                     ] as $option) {
                if (isset($langOptions[$option])) {
                    $this->{'_' . $option} = $langOptions[$option];
                }
            }
        }
        if ($displayLogin) {
            $this->_displayLogin = true;
            foreach ([
                         'isLogged',
                         'userRenderScript'
                     ] as $option) {
                if (isset($loginOptions[$option])) {
                    $this->{'_' . $option} = $loginOptions[$option];
                }
            }
        }

        // Initialisation de la navbar en fonction de l'environnement
        if (defined('APPLICATION_ENV') && APPLICATION_ENV !== 'production') {
            $this->defineEnvironmentLabel();
        }
        $this->render();
    }

    private function defineEnvironmentLabel()
    {
        $this->_labelEnv = APPLICATION_ENV;
        switch (APPLICATION_ENV) {

            case 'development':
                $this->_labelEnvClass = 'label-danger';
                break;

            case 'testing':
                $this->_labelEnvClass = 'label-warning';
                break;

            case 'preprod':
                $this->_labelEnvClass = 'label-primary';
                break;

            default:
                break;

        }
    }

    public function render()
    {
        /** @var Zend_View $view */
        $view = $this->view;
        /** @var Zend_Controller_Request_Http $request */
        $request = Zend_Controller_Front::getInstance()->getRequest();
        ?>
        <nav class="navbar navbar-default navbar-fixed-top" role="navigation" aria-label="Menu">
            <div class="navbar-header ">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#nav-services">
                    <span class="sr-only">Toggle navigation</span> <span class="icon-bar"></span> <span
                            class="icon-bar"></span> <span class="icon-bar"></span>
                </button>

                <div class="logo-episciences">
                    <a class="brand" href="https://www.<?php echo DOMAIN; ?>">
                        <img src="/img/episciences_sign_50x50.png"
                             style="border: 0;"
                             alt="Logo"/></a>
                    <?php
                    if ($this->_labelEnv != false) { ?>
                        <span style="margin-left: 8px;"
                              class="label <?php echo $this->_labelEnvClass; ?>"><?php echo $view->translate($this->_labelEnv); ?></span><?php } ?>
                </div>

            </div>

            <div class="collapse navbar-collapse" id="nav-services">
                <?php
                if ($this->_displayLogin) { ?>
                    <div class="nav navbar-nav navbar-right">
                        <?php
                        try {
                            echo $this->view->render($this->_navBarSearchScript);
                        } catch (Exception $e) {
                            trigger_error('Failed rendering ' . $this->_navBarSearchScript, E_USER_WARNING);
                        }
                        ?>


                        <?php
                        if ($this->_isLogged) {
                            try {
                                echo $this->view->render($this->_userRenderScript);
                            } catch (Exception $e) {
                                trigger_error('Failed rendering ' . $this->_userRenderScript, E_USER_WARNING);
                            }
                        } else { ?>

                            <?php if (defined('RVCODE') && RVCODE !== 'portal'): ?>

                                <a class="btn btn-default navbar-btn"
                                   href="/user/create"> <i class="fas fa-plus"></i>&nbsp;<?= $view->translate('Créer un compte') ?>
                                </a>

                            <?php endif; ?>

                            &nbsp;
                            <form class="form-inline navbar-form pull-right" style="margin-top: 8px; margin-right: 8px;" action="/user/login" id="form-login" method="post">
                                <input type="hidden" name="forward-controller" value="<?php echo $request->getControllerName(); ?>"/>
                                <input type="hidden" name="forward-action" value="<?php echo $request->getActionName(); ?>"/>
                                <?php
                                $forwardParams = $request->getParams();
                                unset($forwardParams['controller'], $forwardParams['action'], $forwardParams['module'], $forwardParams['submit'], $forwardParams['submit_advanced']);

                                foreach ($forwardParams as $name => $value) {
                                    if (is_array($value)) {
                                        echo '<input type="hidden" name="' . htmlspecialchars($name) . '[]" value="' . htmlspecialchars(implode(' OR ', $value)) . '" />';
                                    } else if (is_string($value)) {
                                        echo '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />';
                                    }
                                }
                                ?>


                                <div class="btn-group">


                                    <button class="btn btn-small btn-primary" type="button"
                                            onclick="$('#form-login').submit();" accesskey="l"> <i class="fas fa-sign-in-alt"></i> &nbsp;<?php echo $view->translate('Connexion'); ?></button>
                                    <button class="btn btn-small btn-primary dropdown-toggle" data-toggle="dropdown" type="button">
                                        <span class="caret" style=""></span>
                                    </button>

                                    <ul class="dropdown-menu pull-right">
                                        <li><a href="#"
                                               onclick="$('#form-login').submit();"><?= $this->view->translate('Connexion') ?></a>
                                        </li>
                                        <li class="divider"></li>
                                        <li>
                                            <a href="/user/lostpassword"><?php echo $view->translate('Mot de passe oublié ?'); ?></a>
                                        </li>
                                        <li>
                                            <a href="/user/lostlogin"><?php echo $view->translate('Login oublié ?'); ?></a>
                                        </li>
                                    </ul>
                                </div>
                            </form>
                            <?php
                        } ?>
                    </div>
                    <?php
                } ?>

                <form action="#" method="post" id="formLang" class="nav navbar-nav navbar-right navbar-lang">
                    <input type="hidden" name="lang" id="lang" value="<?= $this->_lang ?>"/>
                    <?php if (count($this->_languages) > 1) : ?>
                        <div>
                            <select id="select-lang" name="Langues" onchange="changeLang(this)">
                                <?php foreach ($this->_languages as $l): ?>
                                    <option value="<?= $l ?>" <?= (($l === $this->_lang) ? 'selected' : '') ?>>  <?= $l ?>  </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </nav>
        <script>
            function changeLang(select) {
                let selectedLang = select.options[select.selectedIndex].value;
                $('#lang').val(selectedLang);
                $('#formLang').submit();
            }
        </script>
        <?php
    }
}

<?php

/**
 * Navbar : Liens vers les différentes applications + lien connexion et langues
 */
class Ccsd_View_Helper_Navbar extends Zend_View_Helper_Abstract
{

    /**
     * Liste des applications
     */

    const APP_EPI = 'epi';

    /**
     * Afficher le choix des langues de l'interface
     *
     * @var bool
     */
    protected $_displayLang = false;

    /**
     * Tableau des langues de l'interface
     *
     * @var array
     */
    protected $_languages = [];

    /**
     * Langue courante de l'interface
     *
     * @var string
     */
    protected $_lang = '';

    /**
     * Afficher le bouton Connexion
     *
     * @var bool
     */
    protected $_displayLogin = false;

    /**
     * Indique si l'utilisateur est connecté
     *
     * @var bool
     */
    protected $_isLogged = false;

    /**
     * Fichier de rendu du bloc "utilisateur connecté"
     *
     * @var string
     */
    protected $_userRenderScript = 'common/user.phtml';

    /**
     * Application active
     *
     * @var string
     */
    protected $_active = self::APP_EPI;

    /**
     * Préfixe des URLs des liens de la navbar
     *
     * @var string
     */
    protected $_prefixUrl = '/';

    protected $_labelEnv = false;

    protected $_labelEnvClass = '';

    /**
     * @param bool $displayLang
     * @param bool $langOptions
     * @param bool $displayLogin
     * @param array $loginOptions
     * @param string $prefixUrl
     * @param string $application
     */
    public function navbar($displayLang, $langOptions, $displayLogin, $loginOptions, $prefixUrl = '/', $application = self::APP_EPI)
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
        $this->_prefixUrl = $prefixUrl;
        $this->_active = $application;

        // Initialisation de la navbar en fonction de l'environnement
        if (defined('APPLICATION_ENV') && APPLICATION_ENV != 'production') {
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
        return $this->render();
    }

    public function render()
    {
        /** @var Zend_View $view */
        $view = $this->view;
        $request = Zend_Controller_Front::getInstance()->getRequest();
        ?>
        <nav class="navbar navbar-default navbar-fixed-top" role="navigation" aria-label="Menu">
            <div class="navbar-header ">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#nav-services">
                    <span class="sr-only">Toggle navigation</span> <span class="icon-bar"></span> <span
                            class="icon-bar"></span> <span class="icon-bar"></span>
                </button>
                <div class="logo-episciences">
                    <a class="brand" href="https://www.episciences.org/"
                       title="Episciences"><img src="/img/episciences_tampon_50x50.png"
                                                                                      style="border: 0;"
                                                                                      alt="Episciences stamp logo"/></a>
                    <?php
                    if ($this->_labelEnv != false) { ?>
                        <span style="margin-left: 8px;"
                              class="label <?php echo $this->_labelEnvClass; ?>"><?php echo $view->translate($this->_labelEnv); ?></span><?php } ?>
                </div>
            </div>
            <!--<?php //Liens services
            ?>-->
            <div class="collapse navbar-collapse" id="nav-services">
                <?php
                if ($this->_displayLogin) { ?>
                    <div class="nav navbar-nav navbar-right">
                        <?php
                        if ($this->_isLogged) {
                            try {
                                echo $this->view->render($this->_userRenderScript);
                            } catch (Exception $e) {
                            }
                        } else { ?>
                            <form class="form-inline pull-right" style="margin-top: 8px; margin-right: 8px;"
                                  action="<?php echo $this->_prefixUrl; ?>user/login" id="form-login" method="post">
                                <input type="hidden" name="forward-controller"
                                       value="<?php echo $request->getControllerName(); ?>"/>
                                <input type="hidden" name="forward-action"
                                       value="<?php echo $request->getActionName(); ?>"/>
                                <?php
                                $forwardParams = $request->getParams();
                                unset($forwardParams['controller'], $forwardParams['action'], $forwardParams['module'], $forwardParams['submit'], $forwardParams['submit_advanced']);

                                foreach ($forwardParams as $name => $value) {
                                    if (is_array($value)) {
                                        if ($name != 'qa') {
                                            echo '<input type="hidden" name="' . htmlspecialchars($name) . '[]" value="' . htmlspecialchars(implode(' OR ', $value)) . '" />';
                                        } else {
                                            // cas particulier pour la recherche avancée
                                            $url = urldecode($_SERVER['REDIRECT_QUERY_STRING']);
                                        }
                                    } else if (is_string($value)) {
                                        echo '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />';
                                    }
                                }

                                if (isset($url)) {
                                    echo '<input type="hidden" name="forward-uri" value="' . htmlspecialchars($url) . '" />';
                                } ?>
                                <div class="btn-group">
                                    <button class="btn btn-small btn-primary" type="button"
                                            onclick="$('#form-login').submit();" accesskey="l">
                                        <?php $view->iconDisplay("glyphicon glyphicon-user glyphicon-white") ?>
                                        &nbsp;<?php echo $view->translate('Connexion'); ?></button>
                                    <button class="btn btn-small btn-primary dropdown-toggle" data-toggle="dropdown"
                                            type="button" style="padding-top: 7px;">
                                        <span class="caret"
                                              style="border-top-color: #fff; border-bottom-color: #fff;"></span>
                                    </button>

                                    <ul class="dropdown-menu pull-right">
                                        <li><a href="#"
                                               onclick="$('#form-login').submit();"><?= $this->view->translate('Connexion'); ?></a>
                                        </li>
                                        <li class="divider"></li>
                                        <li>
                                            <a href="<?php echo $this->_prefixUrl; ?>user/create"><?php echo $view->translate('Créer un compte'); ?></a>
                                        </li>
                                        <li class="divider"></li>
                                        <li>
                                            <a href="<?php echo $this->_prefixUrl; ?>user/lostpassword"><?php echo $view->translate('Mot de passe oublié ?'); ?></a>
                                        </li>
                                        <li>
                                            <a href="<?php echo $this->_prefixUrl; ?>user/lostlogin"><?php echo $view->translate('Login oublié ?'); ?></a>
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
                    <input type="hidden" name="lang" id="lang" value=""/>
                    <?php if (count($this->_languages) > 1) { ?>
                        <div>
                            <select id="select-lang" name="Langues" onchange="changeLang(this)">
                                <?php
                                foreach ($this->_languages as $l) { ?>
                                    <option value="<?php echo $l ?>" <?php echo(($l == $this->_lang) ? 'selected' : ''); ?>>  <?php echo $l ?>  </option>
                                <?php } ?>
                            </select>
                        </div>
                    <?php } ?>
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

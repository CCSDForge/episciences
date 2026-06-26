<?php

/**
 * Class Ccsd_View_Helper_Navbar
 */
class Ccsd_View_Helper_Navbar extends Zend_View_Helper_Abstract
{
    private const LANGUAGE_NAMES = [
        'en' => 'English',
        'fr' => 'Français',
        'es' => 'Español',
        'de' => 'Deutsch',
        'it' => 'Italiano',
        'pt' => 'Português',
        'zh' => '中文',
        'ar' => 'العربية',
        'ru' => 'Русский',
        'ja' => '日本語',
        'ko' => '한국어',
        'nl' => 'Nederlands',
        'pl' => 'Polski',
        'tr' => 'Türkçe',
        'sv' => 'Svenska',
    ];
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

    private function getLanguageName(string $code): string
    {
        return self::LANGUAGE_NAMES[$code] ?? strtoupper($code);
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
                        <img srcset="/img/episciences.svg" src="/img/episciences.png" height="45px"
                             style="border: 0;"
                             alt="Episciences overlay journals"/></a>
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

                <?php if (count($this->_languages) > 1) : ?>
                <form action="#" method="post"
                      id="lang-switcher"
                      class="lang-switcher navbar-right">
                    <input type="hidden" name="lang" id="lang" value="<?= htmlspecialchars($this->_lang) ?>"/>
                    <button
                        type="button"
                        class="lang-switcher__btn"
                        id="lang-switcher-btn"
                        aria-haspopup="listbox"
                        aria-expanded="false"
                        aria-controls="lang-switcher-list"
                        aria-label="<?= $view->translate('Interface language') ?>: <?= htmlspecialchars(strtoupper($this->_lang)) ?>">
                        <i class="fas fa-language" aria-hidden="true"></i>
                        <span class="lang-switcher__code"><?= htmlspecialchars(strtoupper($this->_lang)) ?></span>
                        <i class="fas fa-chevron-down lang-switcher__chevron" aria-hidden="true"></i>
                    </button>
                    <ul
                        class="lang-switcher__list"
                        id="lang-switcher-list"
                        role="listbox"
                        aria-label="<?= $view->translate('Available languages') ?>">
                        <?php foreach ($this->_languages as $l):
                            $selected = ($l === $this->_lang);
                            $code = htmlspecialchars(strtoupper($l));
                            $name = htmlspecialchars($this->getLanguageName($l));
                        ?>
                            <li
                                class="lang-switcher__option<?= $selected ? ' lang-switcher__option--selected' : '' ?>"
                                role="option"
                                aria-selected="<?= $selected ? 'true' : 'false' ?>"
                                tabindex="-1"
                                data-lang="<?= htmlspecialchars($l) ?>">
                                <span class="lang-switcher__option-code"><?= $code ?></span>
                                <span aria-hidden="true"> &ndash; </span>
                                <span class="lang-switcher__option-name"><?= $name ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </form>
                <?php endif; ?>
            </div>
        </nav>
        <script>
            (function () {
                var switcher = document.getElementById('lang-switcher');
                if (!switcher) return;
                var btn = document.getElementById('lang-switcher-btn');
                var list = document.getElementById('lang-switcher-list');
                var langInput = document.getElementById('lang');
                if (!btn || !list || !langInput) return;

                function isOpen() { return list.classList.contains('lang-switcher__list--open'); }

                function open() {
                    btn.setAttribute('aria-expanded', 'true');
                    list.classList.add('lang-switcher__list--open');
                    var active = list.querySelector('[aria-selected="true"]') || list.querySelector('[role="option"]');
                    if (active) active.focus();
                }

                function close() {
                    btn.setAttribute('aria-expanded', 'false');
                    list.classList.remove('lang-switcher__list--open');
                }

                function selectLang(lang) {
                    langInput.value = lang;
                    switcher.submit();
                }

                btn.addEventListener('click', function () { isOpen() ? close() : open(); });

                btn.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); }
                    else if (e.key === 'Escape') { close(); }
                });

                list.addEventListener('keydown', function (e) {
                    var options = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
                    var idx = options.indexOf(document.activeElement);
                    if (e.key === 'ArrowDown') { e.preventDefault(); if (idx < options.length - 1) options[idx + 1].focus(); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); if (idx > 0) options[idx - 1].focus(); else { close(); btn.focus(); } }
                    else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); var el = document.activeElement; if (el && el.dataset && el.dataset.lang) selectLang(el.dataset.lang); }
                    else if (e.key === 'Escape') { close(); btn.focus(); }
                    else if (e.key === 'Tab') { close(); }
                });

                list.querySelectorAll('[role="option"]').forEach(function (opt) {
                    opt.addEventListener('click', function () { selectLang(opt.dataset.lang); });
                });

                document.addEventListener('click', function (e) { if (!switcher.contains(e.target)) close(); });
                document.addEventListener('focusin', function (e) { if (!switcher.contains(e.target)) close(); });
            })();
        </script>
        <?php
    }
}

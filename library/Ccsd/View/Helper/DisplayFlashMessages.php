<?php

declare(strict_types=1);

/**
 * Class Ccsd_View_Helper_DisplayFlashMessages
 *
 * Modernized view helper for rendering flash messages as modern toasts,
 * with full backward-compatibility for existing controllers and fallback layouts.
 */
class Ccsd_View_Helper_DisplayFlashMessages extends Zend_View_Helper_Abstract
{
    // Message severity constants (aligned with historical Bootstrap classes & controllers)
    public const MSG_ERROR = 'danger';
    public const MSG_WARNING = 'warning';
    public const MSG_SUCCESS = 'success';
    public const MSG_INFO = 'info';

    // Legacy error namespace still used by some callers, displayed as MSG_ERROR
    private const MSG_ERROR_LEGACY = 'error';

    // Display order, most severe first
    private const SUPPORTED_NAMESPACES = [
        self::MSG_ERROR,
        self::MSG_ERROR_LEGACY,
        self::MSG_WARNING,
        self::MSG_SUCCESS,
        self::MSG_INFO,
    ];

    /**
     * Markup allowed in flash messages: controllers use links and emphasis,
     * but messages may also embed untrusted data (e.g. exception messages).
     */
    private const PURIFIER_OPTIONS = [
        'HTML.AllowedElements' => ['a', 'b', 'br', 'code', 'em', 'i', 'li', 'ol', 'p', 'span', 'strong', 'u', 'ul'],
        'HTML.AllowedAttributes' => ['a.href', 'a.target'],
        'Attr.AllowedFrameTargets' => ['_blank'],
        'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
    ];

    private static ?Episciences_HTMLPurifier $purifier = null;

    /**
     * Display flash messages from the FlashMessenger action helper.
     *
     * @param string $namespace Specific namespace to display (empty string checks all 4 standard namespaces)
     * @param bool|null $autoclose Whether toasts should automatically dismiss;
     *                             null keeps errors and warnings until dismissed, other types auto-close
     * @return string HTML/JavaScript output for toast rendering
     */
    public function DisplayFlashMessages(string $namespace = '', ?bool $autoclose = null): string
    {
        $namespaces = ($namespace === '')
            ? self::SUPPORTED_NAMESPACES
            : [$namespace];

        $flashMessenger = $this->getFlashMessenger();
        $notifications = [];

        foreach ($namespaces as $ns) {
            $messages = $this->extractMessages($flashMessenger, $ns);
            if (empty($messages)) {
                continue;
            }

            $type = $this->normalizeNamespace($ns);
            $typeAutoclose = $autoclose ?? !in_array($type, ['error', 'warning'], true);

            foreach ($messages as $message) {
                $translated = $this->sanitizeHtml($this->formatMessage($message));
                if ($translated !== '') {
                    $notifications[] = [
                        'type' => $type,
                        'text' => $translated,
                        'autoclose' => $typeAutoclose,
                    ];
                }
            }
        }

        if (empty($notifications)) {
            return '';
        }

        return $this->renderToastOutput($notifications);
    }

    /**
     * Retrieve the FlashMessenger helper instance.
     */
    protected function getFlashMessenger(): Zend_Controller_Action_Helper_FlashMessenger
    {
        /** @var Zend_Controller_Action_Helper_FlashMessenger $flashMessenger */
        $flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        return $flashMessenger;
    }

    /**
     * Extract and clear messages for a given namespace.
     *
     * @return array<int, mixed>
     */
    protected function extractMessages(Zend_Controller_Action_Helper_FlashMessenger $flashMessenger, string $namespace): array
    {
        $flashMessenger->setNamespace($namespace);

        if (!$flashMessenger->hasMessages() && !$flashMessenger->hasCurrentMessages()) {
            return [];
        }

        $messages = array_merge(
            $flashMessenger->getCurrentMessages(),
            $flashMessenger->getMessages()
        );

        $flashMessenger->clearCurrentMessages();
        $flashMessenger->clearMessages();

        return $messages;
    }

    /**
     * Normalize namespace to toast type.
     */
    protected function normalizeNamespace(string $namespace): string
    {
        return match ($namespace) {
            self::MSG_ERROR, self::MSG_ERROR_LEGACY => 'error',
            self::MSG_WARNING => 'warning',
            self::MSG_SUCCESS => 'success',
            self::MSG_INFO => 'info',
            default => 'info',
        };
    }

    /**
     * Translate or format a flash message item.
     *
     * An array without a 'plural' key holds translate() arguments (message id + sprintf values);
     * an array with a 'plural' key is a plural message id handed as-is to the translator.
     */
    protected function formatMessage(mixed $message): string
    {
        if ($this->view === null) {
            return is_array($message) ? implode(' ', array_map('strval', $message)) : (string) $message;
        }

        if (is_array($message) && !array_key_exists('plural', $message)) {
            return (string) $this->view->translate(...$message);
        }

        return (string) $this->view->translate(is_array($message) ? $message : (string) $message);
    }

    /**
     * Strip any markup outside the flash message allowlist (scripts, event handlers, unsafe URLs).
     */
    protected function sanitizeHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        self::$purifier ??= new Episciences_HTMLPurifier(self::PURIFIER_OPTIONS);

        return trim(self::$purifier->purifyHtml($html));
    }

    /**
     * Render the messages as server-side alerts, then hand them over to the toast component.
     *
     * The alerts stay visible when JavaScript is disabled or when the toast bundle is not loaded
     * (e.g. assets not built), so a flash message is never lost. Messages have already been
     * sanitized against an allowlist (links, emphasis), see sanitizeHtml().
     *
     * @param array<int, array{type: string, text: string, autoclose: bool}> $notifications
     */
    protected function renderToastOutput(array $notifications): string
    {
        $jsonPayload = json_encode(
            $notifications,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $closeLabel = $this->view !== null ? (string) $this->view->translate('Fermer') : 'Close';

        $html = [];

        $html[] = '<div class="flash-messages-fallback">';
        foreach ($notifications as $notification) {
            $class = match ($notification['type']) {
                'error' => 'alert alert-danger',
                'warning' => 'alert alert-warning',
                'success' => 'alert alert-success',
                default => 'alert alert-info',
            };
            $html[] = sprintf(
                '<div class="%s"><button type="button" class="close" data-dismiss="alert" aria-label="%s">&times;</button>%s</div>',
                $class,
                htmlspecialchars($closeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $notification['text']
            );
        }
        $html[] = '</div>';

        // The toast bundle is loaded synchronously in <head>: when it is missing, keep the alerts above.
        $html[] = '<script>';
        $html[] = '(function() {';
        $html[] = '    if (!window.toast || typeof window.toast.show !== "function") {';
        $html[] = '        return;';
        $html[] = '    }';
        $html[] = '    var fallback = document.currentScript && document.currentScript.previousElementSibling;';
        $html[] = '    if (fallback && fallback.classList.contains("flash-messages-fallback")) {';
        $html[] = '        fallback.parentNode.removeChild(fallback);';
        $html[] = '    }';
        $html[] = '    var notifications = ' . ($jsonPayload !== false ? $jsonPayload : '[]') . ';';
        $html[] = '    function showToasts() {';
        $html[] = '        notifications.forEach(function(n) {';
        $html[] = '            window.toast.show(n.text, n.type, { autoclose: n.autoclose });';
        $html[] = '        });';
        $html[] = '    }';
        $html[] = '    if (document.readyState === "loading") {';
        $html[] = '        document.addEventListener("DOMContentLoaded", showToasts);';
        $html[] = '    } else {';
        $html[] = '        showToasts();';
        $html[] = '    }';
        $html[] = '})();';
        $html[] = '</script>';

        return implode(PHP_EOL, $html);
    }
}

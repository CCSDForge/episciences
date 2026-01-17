<?php

/**
 * View Helper for rendering user avatar images
 *
 * Provides a consistent, accessible, and maintainable way to display user avatars
 * across the application. Supports multiple sizes, custom classes, and ARIA attributes.
 *
 * @example Compact usage:
 * <?= $this->userAvatar($user, 'thumb') ?>
 *
 * @example Fluent interface usage:
 * <?= $this->userAvatar()
 *       ->setUser($user)
 *       ->setSize('normal')
 *       ->addClass('custom-class')
 *       ->render() ?>
 *
 * @example Advanced usage with accessibility:
 * <?= $this->userAvatar($editor, 'normal')
 *       ->setAlt(sprintf('Profile picture of %s', $editor->getScreenName()))
 *       ->setLazyLoad(true)
 *       ->render() ?>
 */
class Episciences_View_Helper_UserAvatar extends Ccsd_View_Helper_Abstract
{
    /**
     * Valid avatar sizes
     * @var array<string>
     */
    private static array $validSizes = [
        Ccsd_User_Models_User::IMG_NAME_THUMB,
        Ccsd_User_Models_User::IMG_NAME_NORMAL,
        Ccsd_User_Models_User::IMG_NAME_LARGE,
        Ccsd_User_Models_User::IMG_NAME_INITIALS,
    ];
    /**
     * Default CSS class mapping for each size
     * @var array<string, string>
     */
    private static array $sizeClasses = [
        Ccsd_User_Models_User::IMG_NAME_THUMB => 'user-photo-thumb',
        Ccsd_User_Models_User::IMG_NAME_NORMAL => 'user-photo-normal',
        Ccsd_User_Models_User::IMG_NAME_LARGE => 'user-photo-large',
        Ccsd_User_Models_User::IMG_NAME_INITIALS => 'user-photo-thumb',
    ];
    /**
     * User data (array or object with SCREEN_NAME/UID or getScreenName()/getUid())
     * @var array|object|null
     */
    private array|null|object $user;
    /**
     * Avatar size (thumb, normal, large, initials)
     * @var string
     */
    private string $size;
    /**
     * Alt text for the image
     * @var string|null
     */
    private ?string $alt;
    /**
     * Additional CSS classes
     * @var array<string>
     */
    private array $classes = [];
    /**
     * Additional HTML attributes
     * @var array<string, string>
     */
    private array $attributes = [];
    /**
     * Enable lazy loading
     * @var bool
     */
    private bool $lazyLoad = false;
    /**
     * Background color for avatar (hex color code)
     * @var string|null
     */
    private ?string $backgroundColor = null;

    /**
     * Entry point for the helper
     *
     * @param array|object|null $user User data or null to use fluent interface
     * @param string|null $size Avatar size (thumb, normal, large, initials)
     * @return self
     */
    public function userAvatar(array|object|null $user = null, ?string $size = null): self
    {
        // Reset state for new invocation
        $this->reset();

        // If user and size are provided, set them directly
        if ($user !== null) {
            $this->setUser($user);
        }
        if ($size !== null) {
            $this->setSize($size);
        }

        return $this;
    }

    /**
     * Reset helper state
     *
     * @return void
     */
    private function reset(): void
    {
        $this->user = null;
        $this->size = Ccsd_User_Models_User::IMG_NAME_INITIALS;
        $this->alt = null;
        $this->classes = [];
        $this->attributes = [];
        $this->lazyLoad = false;
        $this->backgroundColor = null;
    }

    /**
     * Set the user data
     *
     * @param array|object|null $user User data (array with SCREEN_NAME/UID or object with getters), or null for logged-in user
     * @return self
     * @throws InvalidArgumentException If user data is invalid
     */
    public function setUser(array|object|null $user): self
    {
        // Allow null for logged-in user
        if ($user !== null) {
            // Validate user has required fields
            $screenName = $this->extractScreenName($user);
            $uid = $this->extractUid($user);

            if (empty($screenName) && empty($uid)) {
                throw new InvalidArgumentException('User must have SCREEN_NAME or UID');
            }
        }

        $this->user = $user;
        return $this;
    }

    /**
     * Extract screen name from user data
     *
     * @param array|object $user User data
     * @return string|null
     */
    private function extractScreenName(array|object $user): ?string
    {
        if (is_array($user)) {
            // Check for AVATAR_NAME first (used for anonymous editors)
            if (isset($user['AVATAR_NAME'])) {
                return $user['AVATAR_NAME'];
            }
            return $user['SCREEN_NAME'] ?? $user['screen_name'] ?? null;
        }

        if (is_object($user)) {
            if (method_exists($user, 'getScreenName')) {
                return $user->getScreenName();
            }
            if (isset($user->SCREEN_NAME)) {
                return $user->SCREEN_NAME;
            }
            if (isset($user->screen_name)) {
                return $user->screen_name;
            }
        }

        return null;
    }

    /**
     * Extract UID from user data
     *
     * @param array|object $user User data
     * @return int|null
     */
    private function extractUid(array|object $user): ?int
    {
        $uid = null;

        if (is_array($user)) {
            $uid = $user['UID'] ?? $user['uid'] ?? null;
        } elseif (is_object($user)) {
            if (method_exists($user, 'getUid')) {
                $uid = $user->getUid();
            } elseif (isset($user->UID)) {
                $uid = $user->UID;
            } elseif (isset($user->uid)) {
                $uid = $user->uid;
            }
        }

        return $uid !== null ? (int)$uid : null;
    }

    /**
     * Set the avatar size
     *
     * @param string $size Size (thumb, normal, large, initials)
     * @return self
     * @throws InvalidArgumentException If size is invalid
     */
    public function setSize(string $size): self
    {
        if (!in_array($size, self::$validSizes, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid size "%s". Valid sizes are: %s',
                    $size,
                    implode(', ', self::$validSizes)
                )
            );
        }

        $this->size = $size;
        return $this;
    }

    /**
     * Set custom alt text
     *
     * @param string $alt Alt text
     * @return self
     */
    public function setAlt(string $alt): self
    {
        $this->alt = $alt;
        return $this;
    }

    /**
     * Add a CSS class
     *
     * @param string $class CSS class name
     * @return self
     */
    public function addClass(string $class): self
    {
        $this->classes[] = $class;
        return $this;
    }

    /**
     * Set an HTML attribute
     *
     * @param string $name Attribute name
     * @param string $value Attribute value
     * @return self
     */
    public function setAttribute(string $name, string $value): self
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    /**
     * Enable or disable lazy loading
     *
     * @param bool $enabled Enable lazy loading
     * @return self
     */
    public function setLazyLoad(bool $enabled = true): self
    {
        $this->lazyLoad = $enabled;
        return $this;
    }

    /**
     * Set background color for avatar
     *
     * @param string $color Hex color code (e.g., '#FF5722', 'FF5722')
     * @return self
     */
    public function setBackgroundColor(string $color): self
    {
        // Remove # if present
        $color = ltrim($color, '#');

        // Validate hex color
        if (preg_match('/^[0-9A-Fa-f]{6}$/', $color)) {
            $this->backgroundColor = $color;
        }

        return $this;
    }

    /**
     * Convert to string (calls render())
     *
     * @return string
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (Exception $e) {
            // Log error and return empty string
            trigger_error($e->getMessage(), E_USER_WARNING);
            return '';
        }
    }

    /**
     * Render the avatar HTML
     *
     * @return string
     */
    public function render(): string
    {
        // User can be null (logged-in user)
        $url = $this->generateUrl();
        $alt = $this->generateAltText();
        $classes = $this->generateClasses();
        $attributes = $this->generateAttributes();

        // Build HTML
        $html = '<img src="' . $url . '"';

        if ($classes) {
            $html .= ' class="' . $classes . '"';
        }

        $html .= ' alt="' . $alt . '"';

        if ($attributes) {
            $html .= ' ' . $attributes;
        }

        $html .= '>';

        return $html;
    }

    /**
     * Generate the avatar URL
     *
     * @return string
     */
    private function generateUrl(): string
    {
        $screenName = null;
        $uid = null;

        // Extract user data if user is provided
        if ($this->user !== null) {
            $screenName = $this->extractScreenName($this->user);
            $uid = $this->extractUid($this->user);
        }

        // Build URL components
        $urlParts = ['/user/photo'];

        // Add name parameter for initials generation
        if ($screenName && $this->size === Ccsd_User_Models_User::IMG_NAME_INITIALS) {
            $urlParts[] = 'name';
            $urlParts[] = rawurlencode($screenName);
        }

        // Add UID parameter (if not null, otherwise it means logged-in user)
        if ($uid) {
            $urlParts[] = 'uid';
            $urlParts[] = $uid;
        }

        // Add size parameter
        $urlParts[] = 'size';
        $urlParts[] = $this->size;

        // Add background color parameter if set
        if ($this->backgroundColor !== null) {
            $urlParts[] = 'bgcolor';
            $urlParts[] = $this->backgroundColor;
        }

        // Build URL with cache busting version parameter
        $url = implode('/', $urlParts);
        $url .= '?v=' . Episciences_Auth::getPhotoVersion();

        return $url;
    }

    /**
     * Generate alt text
     *
     * @return string
     */
    private function generateAltText(): string
    {
        // Use custom alt text if provided
        if ($this->alt !== null) {
            return $this->view->escape($this->alt);
        }

        // Generate alt text from screen name
        if ($this->user !== null) {
            $screenName = $this->extractScreenName($this->user);
            if ($screenName) {
                return $this->view->escape($screenName);
            }
        } else {
            // User is logged-in user, use their screen name
            if (Episciences_Auth::isLogged()) {
                $screenName = Episciences_Auth::getScreenName();
                if ($screenName) {
                    return $this->view->escape($screenName);
                }
            }
        }

        // Fallback to generic alt text
        return 'avatar';
    }

    /**
     * Generate CSS classes
     *
     * @return string
     */
    private function generateClasses(): string
    {
        $classes = [];

        // Add default size class
        if (isset(self::$sizeClasses[$this->size])) {
            $classes[] = self::$sizeClasses[$this->size];
        }

        // Add custom classes
        foreach ($this->classes as $class) {
            $classes[] = $this->view->escape($class);
        }

        return implode(' ', $classes);
    }

    /**
     * Generate HTML attributes
     *
     * @return string
     */
    private function generateAttributes(): string
    {
        $attributes = [];

        // Add lazy loading if enabled
        if ($this->lazyLoad) {
            $attributes[] = 'loading="lazy"';
        }

        // Add custom attributes
        foreach ($this->attributes as $name => $value) {
            $escapedName = $this->view->escape($name);
            $escapedValue = $this->view->escape($value);
            $attributes[] = sprintf('%s="%s"', $escapedName, $escapedValue);
        }

        return implode(' ', $attributes);
    }
}

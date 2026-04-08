<?php

namespace unit\library\Episciences\View\Helper;

use Episciences_View_Helper_UserAvatar;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zend_View;

/**
 * Unit tests for Episciences_View_Helper_UserAvatar
 *
 * @covers Episciences_View_Helper_UserAvatar
 */
class UserAvatarTest extends TestCase
{
    /**
     * @var Episciences_View_Helper_UserAvatar
     */
    private $helper;

    /**
     * @var Zend_View
     */
    private $view;

    protected function setUp(): void
    {
        // Create a real Zend_View instance
        $this->view = new Zend_View();

        // Create helper instance
        $this->helper = new Episciences_View_Helper_UserAvatar();
        $this->helper->setView($this->view);
    }

    /**
     * Test basic rendering with array user data
     */
    public function testRenderWithArrayUserData(): void
    {
        $user = [
            'SCREEN_NAME' => 'John Doe',
            'UID' => 123
        ];

        $html = $this->helper->userAvatar($user, 'initials')->render();

        $this->assertStringContainsString('<img src="/user/photo/name/John%20Doe/uid/123/size/initials', $html);
        $this->assertStringContainsString('class="user-photo-thumb"', $html);
        $this->assertStringContainsString('alt="John Doe"', $html);
    }

    /**
     * Test basic rendering with object user data
     */
    public function testRenderWithObjectUserData(): void
    {
        $user = new MockUser('Jane Smith', 456);

        $html = $this->helper->userAvatar($user, 'thumb')->render();

        $this->assertStringContainsString('<img src="/user/photo/uid/456/size/thumb', $html);
        $this->assertStringContainsString('class="user-photo-thumb"', $html);
        $this->assertStringContainsString('alt="Jane Smith"', $html);
    }

    /**
     * Test size validation - valid sizes
     */
    public function testSetSizeWithValidSizes(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];

        // Test all valid sizes
        $validSizes = ['thumb', 'normal', 'large', 'initials'];

        foreach ($validSizes as $size) {
            $html = $this->helper->userAvatar($user, $size)->render();
            $this->assertStringContainsString("size/$size", $html);
        }
    }

    /**
     * Test size validation - invalid size throws exception
     */
    public function testSetSizeWithInvalidSizeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid size "invalid"');

        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];
        $this->helper->userAvatar($user, 'invalid');
    }

    /**
     * Test custom alt text
     */
    public function testCustomAltText(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];

        $html = $this->helper->userAvatar($user, 'thumb')
            ->setAlt('Custom alt text')
            ->render();

        $this->assertStringContainsString('alt="Custom alt text"', $html);
        $this->assertStringNotContainsString('alt="Test"', $html);
    }

    /**
     * Test adding custom CSS class
     */
    public function testAddClass(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];

        $html = $this->helper->userAvatar($user, 'thumb')
            ->addClass('custom-class')
            ->render();

        $this->assertStringContainsString('class="user-photo-thumb custom-class"', $html);
    }

    /**
     * Test adding multiple custom CSS classes
     */
    public function testAddMultipleClasses(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];

        $html = $this->helper->userAvatar($user, 'thumb')
            ->addClass('class-1')
            ->addClass('class-2')
            ->addClass('class-3')
            ->render();

        $this->assertStringContainsString('class="user-photo-thumb class-1 class-2 class-3"', $html);
    }

    /**
     * Test lazy loading
     */
    public function testLazyLoading(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];

        $html = $this->helper->userAvatar($user, 'thumb')
            ->setLazyLoad(true)
            ->render();

        $this->assertStringContainsString('loading="lazy"', $html);
    }

    /**
     * Test lazy loading disabled
     */
    public function testLazyLoadingDisabled(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];

        $html = $this->helper->userAvatar($user, 'thumb')
            ->setLazyLoad(false)
            ->render();

        $this->assertStringNotContainsString('loading="lazy"', $html);
    }

    /**
     * Test custom HTML attributes
     */
    public function testSetAttribute(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];

        $html = $this->helper->userAvatar($user, 'thumb')
            ->setAttribute('data-testid', 'user-avatar')
            ->setAttribute('aria-label', 'User photo')
            ->render();

        $this->assertStringContainsString('data-testid="user-avatar"', $html);
        $this->assertStringContainsString('aria-label="User photo"', $html);
    }

    /**
     * Test XSS protection in screen name
     */
    public function testXssProtectionInScreenName(): void
    {
        $user = [
            'SCREEN_NAME' => '<script>alert("xss")</script>',
            'UID' => 1
        ];

        $html = $this->helper->userAvatar($user, 'thumb')->render();

        // Should be escaped
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * Test XSS protection in custom class
     */
    public function testXssProtectionInClass(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];

        $html = $this->helper->userAvatar($user, 'thumb')
            ->addClass('"><script>alert("xss")</script>')
            ->render();

        // Should be escaped
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * Test XSS protection in attributes
     */
    public function testXssProtectionInAttributes(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];

        $html = $this->helper->userAvatar($user, 'thumb')
            ->setAttribute('data-test', '"><script>alert("xss")</script>')
            ->render();

        // Should be escaped
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * Test URL generation with initials size includes name parameter
     */
    public function testUrlGenerationWithInitialsIncludesName(): void
    {
        $user = ['SCREEN_NAME' => 'John Doe', 'UID' => 123];

        $html = $this->helper->userAvatar($user, 'initials')->render();

        $this->assertStringContainsString('/user/photo/name/John%20Doe/uid/123/size/initials', $html);
    }

    /**
     * Test URL generation with non-initials size excludes name parameter
     */
    public function testUrlGenerationWithoutInitialsExcludesName(): void
    {
        $user = ['SCREEN_NAME' => 'John Doe', 'UID' => 123];

        $html = $this->helper->userAvatar($user, 'thumb')->render();

        $this->assertStringNotContainsString('/name/', $html);
        $this->assertStringContainsString('/uid/123/size/thumb', $html);
    }

    /**
     * Test user with only UID (no screen name)
     */
    public function testUserWithOnlyUid(): void
    {
        $user = ['UID' => 999];

        $html = $this->helper->userAvatar($user, 'thumb')->render();

        $this->assertStringContainsString('/uid/999/size/thumb', $html);
        $this->assertStringContainsString('alt="avatar"', $html); // Fallback alt text
    }

    /**
     * Test user with only screen name (no UID)
     */
    public function testUserWithOnlyScreenName(): void
    {
        $user = ['SCREEN_NAME' => 'Anonymous'];

        $html = $this->helper->userAvatar($user, 'initials')->render();

        $this->assertStringContainsString('/name/Anonymous', $html);
        $this->assertStringContainsString('alt="Anonymous"', $html);
    }

    /**
     * Test invalid user data throws exception
     */
    public function testInvalidUserDataThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User must have SCREEN_NAME or UID');

        $user = ['INVALID_FIELD' => 'value'];
        $this->helper->userAvatar($user, 'thumb');
    }

    /**
     * Test fluent interface returns self
     */
    public function testFluentInterface(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];

        $result1 = $this->helper->userAvatar($user, 'thumb');
        $this->assertInstanceOf(Episciences_View_Helper_UserAvatar::class, $result1);

        $result2 = $result1->setSize('normal');
        $this->assertInstanceOf(Episciences_View_Helper_UserAvatar::class, $result2);

        $result3 = $result2->setAlt('Test');
        $this->assertInstanceOf(Episciences_View_Helper_UserAvatar::class, $result3);

        $result4 = $result3->addClass('test');
        $this->assertInstanceOf(Episciences_View_Helper_UserAvatar::class, $result4);

        $result5 = $result4->setAttribute('test', 'value');
        $this->assertInstanceOf(Episciences_View_Helper_UserAvatar::class, $result5);

        $result6 = $result5->setLazyLoad(true);
        $this->assertInstanceOf(Episciences_View_Helper_UserAvatar::class, $result6);
    }

    /**
     * Test __toString magic method
     */
    public function testToString(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];

        $helper = $this->helper->userAvatar($user, 'thumb');
        $html = (string) $helper;

        $this->assertStringContainsString('<img src=', $html);
        $this->assertStringContainsString('alt="Test"', $html);
    }

    /**
     * Test different size classes
     */
    public function testSizeClassMapping(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 1];

        $sizeClassMap = [
            'thumb' => 'user-photo-thumb',
            'normal' => 'user-photo-normal',
            'large' => 'user-photo-large',
            'initials' => 'user-photo-thumb',
        ];

        foreach ($sizeClassMap as $size => $expectedClass) {
            $html = $this->helper->userAvatar($user, $size)->render();
            $this->assertStringContainsString("class=\"$expectedClass\"", $html);
        }
    }

    /**
     * Test lowercase field names (screen_name and uid)
     */
    public function testLowercaseFieldNames(): void
    {
        $user = [
            'screen_name' => 'Test User',
            'uid' => 789
        ];

        $html = $this->helper->userAvatar($user, 'thumb')->render();

        $this->assertStringContainsString('/uid/789', $html);
        $this->assertStringContainsString('alt="Test User"', $html);
    }

    /**
     * Test with object having properties instead of getters
     */
    public function testObjectWithProperties(): void
    {
        $user = new stdClass();
        $user->SCREEN_NAME = 'Property User';
        $user->UID = 555;

        $html = $this->helper->userAvatar($user, 'thumb')->render();

        $this->assertStringContainsString('/uid/555', $html);
        $this->assertStringContainsString('alt="Property User"', $html);
    }

    /**
     * Test that UID=0 is treated as absent (falsy) and excluded from the generated URL.
     * Documents the known behavior: callers should not pass UID=0 expecting it in the URL.
     */
    public function testUserWithUidZeroExcludedFromUrl(): void
    {
        $user = ['SCREEN_NAME' => 'Test', 'UID' => 0];

        $html = $this->helper->userAvatar($user, 'thumb')->render();

        // UID=0 is falsy: it is NOT added to the URL
        $this->assertStringNotContainsString('/uid/', $html);
    }

    /**
     * Test state reset between invocations
     */
    public function testStateResetBetweenInvocations(): void
    {
        $user1 = ['SCREEN_NAME' => 'User 1', 'UID' => 1];
        $user2 = ['SCREEN_NAME' => 'User 2', 'UID' => 2];

        // First invocation with custom class
        $html1 = $this->helper->userAvatar($user1, 'thumb')
            ->addClass('custom-class')
            ->render();

        $this->assertStringContainsString('custom-class', $html1);

        // Second invocation should not have the custom class
        $html2 = $this->helper->userAvatar($user2, 'thumb')->render();

        $this->assertStringNotContainsString('custom-class', $html2);
    }
}

/**
 * Mock User class for testing
 */
class MockUser
{
    private $screenName;
    private $uid;

    public function __construct(string $screenName, int $uid)
    {
        $this->screenName = $screenName;
        $this->uid = $uid;
    }

    public function getScreenName(): string
    {
        return $this->screenName;
    }

    public function getUid(): int
    {
        return $this->uid;
    }
}

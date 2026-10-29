const { defineConfig, devices } = require('@playwright/test');

/**
 * Episciences E2E Testing Configuration (Playwright)
 *
 * Supports both URL routing architectures:
 * - Journal mode (main / staging): https://dev.episciences.org/
 * - Manager mode (main-epi-manager / preprod-epi-manager): https://manager-dev.episciences.org/dev/
 */
const isManager = process.env.APP_MODE === 'manager';
const defaultBaseUrl = isManager
    ? 'https://manager-dev.episciences.org/dev/'
    : 'https://dev.episciences.org/';

const rawBaseUrl = process.env.BASE_URL || defaultBaseUrl;
// Ensure trailing slash so relative paths ('submit', 'browse') resolve correctly in both modes
const baseURL = rawBaseUrl.endsWith('/') ? rawBaseUrl : `${rawBaseUrl}/`;

module.exports = defineConfig({
    testDir: './tests/e2e',
    timeout: 30 * 1000,
    expect: {
        timeout: 5000
    },
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }]
    ],
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'on-first-retry',
        ignoreHTTPSErrors: true // Required for local self-signed certificates in dev/Traefik
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] }
        }
    ]
});

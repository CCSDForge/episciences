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
    // Seeds the E2E accounts and removes what previous runs left in the journal
    globalSetup: require.resolve('./tests/e2e/global-setup.js'),
    timeout: 30 * 1000,
    expect: {
        timeout: 5000
    },
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    // No retry: an editorial workflow test cannot run twice on the same data (an arXiv preprint is
    // submitted only once), the global setup has to clean the journal first
    retries: 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }]
    ],
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        ignoreHTTPSErrors: true // Required for local self-signed certificates in dev/Traefik
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] }
        }
    ]
});

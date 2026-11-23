const { defineConfig, devices } = require('@playwright/test');

// Journal mode (main / staging) or manager mode (*-epi-manager): see tests/e2e/helpers/app-url.js
const { baseURL } = require('./tests/e2e/helpers/app-url');

const origin = new URL(baseURL).origin;
// Web Crypto (used by the ALTCHA widget) requires a secure context; CI serves the app over plain HTTP
const chromiumArgs = origin.startsWith('http://')
    ? [`--unsafely-treat-insecure-origin-as-secure=${origin}`]
    : [];

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
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
        // Failures annotated on the pull request diff
        ...(process.env.CI ? [['github']] : [])
    ],
    use: {
        baseURL,
        // The interface language follows the browser one, and dates are rendered in the local time
        locale: 'en-US',
        timezoneId: 'Europe/Paris',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        ignoreHTTPSErrors: true // Required for local self-signed certificates in dev/Traefik
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                // chrome-headless-shell ignores --unsafely-treat-insecure-origin-as-secure, full chromium honors it
                ...(chromiumArgs.length > 0
                    ? { channel: 'chromium', launchOptions: { args: chromiumArgs } }
                    : {}),
            }
        }
    ]
});

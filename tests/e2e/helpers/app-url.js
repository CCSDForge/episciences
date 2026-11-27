/**
 * Base URL of the application under test, shared by playwright.config.js and the helpers.
 *
 * Two URL routing architectures are supported:
 * - Journal mode (main / staging): https://dev.episciences.org/
 * - Manager mode (main-epi-manager / preprod-epi-manager): https://manager-dev.episciences.org/dev/
 *   every journal is served under its code, so every application URL carries the /dev/ prefix
 *
 * Tests navigate with paths relative to the base URL (`page.goto('paper/view')`, never
 * `page.goto('/paper/view')`) so that the same suite runs in both modes.
 */
const isManager = process.env.APP_MODE === 'manager';
const defaultBaseUrl = isManager ? 'https://manager-dev.episciences.org/dev/' : 'https://dev.episciences.org/';

const rawBaseUrl = process.env.BASE_URL || defaultBaseUrl;
// Ensure trailing slash so relative paths ('submit', 'browse') resolve correctly in both modes
const baseURL = rawBaseUrl.endsWith('/') ? rawBaseUrl : `${rawBaseUrl}/`;
const basePath = new URL(baseURL).pathname;

/**
 * Turns an application link (absolute URL read in an email, or root-relative href read in a page)
 * into a path relative to the base URL, to be passed to `page.goto()` or `page.request.get()`.
 *
 * The link targets the configured application URL, whose host may differ from the one the tests
 * reach: only its path and query string are kept. In manager mode the link must carry the journal
 * prefix; a link without it is reported, as it would be a broken link for the users.
 * @param {string} link absolute URL or root-relative path
 * @returns {string} path relative to the base URL, query string included
 */
function toAppPath(link) {
    const url = new URL(link, baseURL);
    if (!url.pathname.startsWith(basePath)) {
        throw new Error(`Link outside of the application base path "${basePath}": ${link}`);
    }
    return url.pathname.slice(basePath.length) + url.search;
}

module.exports = { isManager, baseURL, basePath, toAppPath };

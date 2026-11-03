const { request } = require('@playwright/test');
const { flushMailQueue } = require('./app-cli');

/**
 * Mailpit REST API client for the E2E tests.
 *
 * The application does not send emails itself: it queues them on disk and a cron sends them. Each
 * polling round flushes that queue first (see app-cli.js), then searches Mailpit.
 *
 * Tests run in parallel and share the same mailbox: never empty it, look for a message with criteria
 * that only the current test can match instead (the paper id is part of every paper-related subject).
 */
class MailpitClient {
    /**
     * @param {string} [baseUrl] defaults to MAILPIT_URL, then to the docker-compose Mailpit host
     */
    constructor(baseUrl) {
        this.baseUrl = (baseUrl || process.env.MAILPIT_URL || 'https://mailpit.episciences.org').replace(/\/+$/, '');
        this.api = null;
    }

    async getApi() {
        if (!this.api) {
            this.api = await request.newContext({ baseURL: `${this.baseUrl}/`, ignoreHTTPSErrors: true });
        }
        return this.api;
    }

    async dispose() {
        if (this.api) {
            await this.api.dispose();
            this.api = null;
        }
    }

    /**
     * @param {string} query Mailpit search query, for instance: to:"someone@example.org" subject:"#123"
     * @returns {Promise<Array<Object>>} message summaries, most recent first
     */
    async search(query) {
        const api = await this.getApi();
        const response = await api.get('api/v1/search', { params: { query } });
        if (!response.ok()) {
            throw new Error(`Mailpit search failed with HTTP ${response.status()}`);
        }
        return (await response.json()).messages || [];
    }

    /**
     * @param {string} id
     * @returns {Promise<Object>} the full message, with its Text and HTML bodies
     */
    async getMessage(id) {
        const api = await this.getApi();
        const response = await api.get(`api/v1/message/${id}`);
        if (!response.ok()) {
            throw new Error(`Mailpit message ${id} could not be read: HTTP ${response.status()}`);
        }
        return response.json();
    }

    /**
     * Waits for the first email sent to a recipient whose subject contains every given fragment.
     * @param {Object} options
     * @param {string} options.to recipient address
     * @param {string[]} [options.subjectContains] case-sensitive subject fragments
     * @param {number} [options.timeoutMs=30000]
     * @returns {Promise<Object>} the full message
     */
    async waitForEmail({ to, subjectContains = [], timeoutMs = 30000 }) {
        const deadline = Date.now() + timeoutMs;

        while (Date.now() < deadline) {
            flushMailQueue();

            const match = (await this.search(`to:"${to}"`)).find(message =>
                subjectContains.every(fragment => (message.Subject || '').includes(fragment))
            );

            if (match) {
                return this.getMessage(match.ID);
            }

            await new Promise(resolve => setTimeout(resolve, 1000));
        }

        throw new Error(`No email to ${to} with a subject containing ${JSON.stringify(subjectContains)} within ${timeoutMs} ms`);
    }

    /**
     * @param {string} content text or HTML body
     * @returns {string[]} every HTTP(S) link it contains
     */
    extractLinks(content) {
        if (!content) {
            return [];
        }
        return content.match(/https?:\/\/[^\s"'<>\]]+/g) || [];
    }
}

module.exports = { MailpitClient };

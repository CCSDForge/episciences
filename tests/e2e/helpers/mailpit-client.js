/**
 * Mailpit REST API Client for Playwright tests
 * Allows polling, inspecting and extracting confirmation / invitation links from sent emails.
 */
class MailpitClient {
    /**
     * @param {string} [baseUrl]
     */
    constructor(baseUrl) {
        // Priority: explicit argument -> env MAILPIT_URL -> default host container bridge -> localhost
        this.baseUrl = (baseUrl || process.env.MAILPIT_URL || 'http://172.18.0.12:8025').replace(/\/+$/, '');
    }

    /**
     * Retrieve all messages stored in Mailpit
     * @returns {Promise<Array<Object>>}
     */
    async getMessages() {
        try {
            const response = await fetch(`${this.baseUrl}/api/v1/messages`);
            if (!response.ok) {
                return [];
            }
            const data = await response.json();
            return data.messages || [];
        } catch (error) {
            return [];
        }
    }

    /**
     * Wait for an email matching criteria (recipient, subject substring)
     * @param {Object} options
     * @param {string} [options.to]
     * @param {string} [options.subject]
     * @param {number} [options.timeoutMs=10000]
     * @param {number} [options.pollIntervalMs=500]
     * @returns {Promise<Object|null>}
     */
    async waitForEmail({ to, subject, timeoutMs = 10000, pollIntervalMs = 500 }) {
        const startTime = Date.now();

        while (Date.now() - startTime < timeoutMs) {
            const messages = await this.getMessages();

            const match = messages.find(msg => {
                const matchesTo = !to || (msg.To && msg.To.some(t => t.Address.toLowerCase().includes(to.toLowerCase())));
                const matchesSubject = !subject || (msg.Subject && msg.Subject.includes(subject));
                return matchesTo && matchesSubject;
            });

            if (match) {
                // Fetch full message detail including HTML/text bodies
                try {
                    const detailRes = await fetch(`${this.baseUrl}/api/v1/message/${match.ID}`);
                    if (detailRes.ok) {
                        return await detailRes.json();
                    }
                } catch {
                    return match;
                }
            }

            await new Promise(resolve => setTimeout(resolve, pollIntervalMs));
        }

        return null;
    }

    /**
     * Extract all HTTP/HTTPS links found in a text or HTML body
     * @param {string} content
     * @returns {string[]}
     */
    extractLinks(content) {
        if (!content) return [];
        const regex = /https?:\/\/[^\s"'<>]+/g;
        return content.match(regex) || [];
    }

    /**
     * Delete all messages stored in Mailpit
     * @returns {Promise<boolean>}
     */
    async deleteAllMessages() {
        try {
            const res = await fetch(`${this.baseUrl}/api/v1/messages`, { method: 'DELETE' });
            return res.ok;
        } catch {
            return false;
        }
    }
}

module.exports = { MailpitClient };

/**
 * Submission form - identifier parsing
 *
 * Turns what the author pasted in the identifier field (a bare identifier or a
 * repository URL) into the identifier expected by the repository and, when it
 * can be found, the document version. Pure functions: no DOM access, the
 * caller fills the form fields with the returned values.
 *
 * @module SubmitIdentifier
 */
const SubmitIdentifier = (() => {
    /**
     * Repository-specific URL patterns: the first capture group is the identifier.
     */
    const URL_RULES = [
        // arXiv: /abs/, /pdf/ and /html/ pages; keeps the old-style archive prefix (hep-th/9704188)
        {
            host: /(^|\.)arxiv\.org$/,
            path: /^\/(?:abs|pdf|html)\/(.+?)(?:\.pdf)?\/?$/,
        },
        // bioRxiv / medRxiv: /content/<DOI>[vN][.full[.pdf]], any DOI prefix (10.1101, 10.64898...)
        {
            host: /(^|\.)(bio|med)rxiv\.org$/,
            path: /^\/content\/(10\.\d{4,9}\/[^/]+?)(?:\.full(?:\.pdf(?:\+html)?)?|\.abstract|\.supplementary-material)?\/?$/,
        },
        // Zenodo: /record/<id>, /records/<id>[/files/...] and /doi/10.5281/zenodo.<id>
        {
            host: /(^|\.)zenodo\.org$/,
            path: /^\/(?:records?|doi\/10\.5281\/zenodo\.)\/?(\d+)/,
        },
        // IACR Cryptology ePrint Archive: /<year>/<number>[.pdf]
        {
            host: /^(eprint\.iacr\.org|ia\.cr)$/,
            path: /^\/(\d{4}\/\d+)(?:\.pdf)?\/?$/,
        },
    ];

    // HAL (any portal): the first path segment is the identifier, e.g. /hal-01234567v2/document
    const HAL_PATH =
        /^\/([a-z][a-z0-9_]*(?:-[a-z][a-z0-9_]*)*-\d{6,}(?:v\d+)?)(?:\/|$)/i;

    // "v0" is not a valid version
    const VERSION_SUFFIX = /^(.+?)v([1-9]\d*)$/;

    // Version used for a URL that does not give one, as done server-side
    // (@see SubmitController::extractVersion())
    const DEFAULT_VERSION = 1;

    /**
     * Version as a positive number ("2" => 2, "1.1" => 1.1), or null.
     *
     * @param {string|null} value
     * @returns {number|null}
     */
    function toVersion(value) {
        const version = parseFloat(value);
        return Number.isFinite(version) && version > 0 ? version : null;
    }

    /**
     * Split a trailing version suffix ("v4") from an identifier.
     *
     * A trailing "vN" is only a version marker for repositories where the
     * version must be given (HAL, arXiv...). Repositories whose version is
     * auto-detected (isRequiredVersion === false: Zenodo, ARCHE, BAOBAB...) can
     * have identifiers ending in "v" + digits by coincidence of their own slug
     * format (e.g. BAOBAB's "s9g8r-v1011"), which must not be split.
     *
     * @param {string} identifier
     * @param {boolean} [isRequiredVersion=true]
     * @returns {{identifier: string, version: number|null}}
     */
    function splitVersion(identifier, isRequiredVersion = true) {
        const match = isRequiredVersion
            ? identifier.match(VERSION_SUFFIX)
            : null;

        return match
            ? { identifier: match[1], version: toVersion(match[2]) }
            : { identifier, version: null };
    }

    /**
     * Identifier found by a repository-specific URL pattern, or null.
     *
     * @param {URL} url
     * @returns {string|null}
     */
    function matchUrlRule(url) {
        for (const rule of URL_RULES) {
            const match =
                rule.host.test(url.hostname) && url.pathname.match(rule.path);
            if (match) {
                return decodeURIComponent(match[1]);
            }
        }

        const hal = url.pathname.match(HAL_PATH);
        return hal ? hal[1] : null;
    }

    /**
     * Generic extraction for URLs no repository-specific pattern applies to.
     *
     * @param {URL} url
     * @param {boolean} isDataverseRepo
     * @returns {string}
     */
    function genericUrlIdentifier(url, isDataverseRepo) {
        let identifier;

        if (isDataverseRepo || url.search) {
            identifier = url.pathname.replace(/^\/+|\/+$/g, '');
        } else {
            identifier = url.pathname
                .replace(/^\/+/, '') // Remove leading slashes
                .replace(/\/\w+\/$/, '') // Remove trailing /word/ pattern
                .replace(/\/+$/, ''); // Remove trailing slashes
        }

        return identifier.trim() ? identifier : url.href;
    }

    /**
     * Parse an http(s) URL; null when the input is not one.
     *
     * @param {string} input
     * @param {boolean} isDataverseRepo
     * @returns {{identifier: string, version: number|null}|null}
     */
    function parseUrl(input, isDataverseRepo) {
        if (!/^https?:\/\//i.test(input)) {
            return null;
        }

        let url;
        try {
            url = new URL(input);
        } catch (error) {
            return null;
        }

        // Dataverse: ?persistentId=doi:...&version=1.1
        const persistentId = url.searchParams.get('persistentId');
        if (persistentId) {
            return {
                identifier: persistentId,
                version: toVersion(url.searchParams.get('version')),
            };
        }

        return {
            identifier:
                matchUrlRule(url) ?? genericUrlIdentifier(url, isDataverseRepo),
            version: null,
        };
    }

    /**
     * Extract the identifier and the version from the identifier field input.
     *
     * For a URL, the version is always set when the repository requires one:
     * the version given by the URL, DEFAULT_VERSION otherwise.
     *
     * @param {string} input - bare identifier or repository URL
     * @param {Object} [options]
     * @param {boolean} [options.isDataverseRepo=false]
     * @param {boolean} [options.isRequiredVersion=true]
     * @returns {{identifier: string, version: number|null}} version is null when none was found
     */
    function parse(input, options = {}) {
        const { isDataverseRepo = false, isRequiredVersion = true } = options;
        const trimmed = input.trim();
        const fromUrl = parseUrl(trimmed, isDataverseRepo);

        if (!fromUrl) {
            return splitVersion(
                trimmed.replace(/^arxiv:\s*/i, ''),
                isRequiredVersion
            );
        }

        if (!isRequiredVersion) {
            return { identifier: fromUrl.identifier, version: null };
        }

        if (fromUrl.version !== null) {
            return fromUrl;
        }

        const { identifier, version } = splitVersion(fromUrl.identifier);
        return { identifier, version: version ?? DEFAULT_VERSION };
    }

    return { parse, splitVersion, matchUrlRule };
})();

// CommonJS export for Jest testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SubmitIdentifier;
}

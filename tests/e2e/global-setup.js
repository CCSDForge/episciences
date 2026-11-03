const { seedAndReset, restoreSettings } = require('./helpers/app-cli');

/**
 * Runs once before the whole suite: every run starts from the same accounts and from a journal free
 * of what previous runs created. Set E2E_SKIP_SEED=1 to skip it (for instance against an environment
 * where the PHP scripts cannot be run).
 *
 * Returns a teardown function executed after all tests finish to restore initial journal settings.
 */
module.exports = async () => {
    if (process.env.E2E_SKIP_SEED === '1') {
        return;
    }

    const output = seedAndReset();
    const resetLine = output.split('\n').find(line => line.startsWith('Reset:'));

    if (resetLine) {
        console.log(`[e2e] ${resetLine}`);
    }

    return async () => {
        try {
            restoreSettings();
            console.log('[e2e] Journal settings restored to their initial state.');
        } catch (error) {
            console.error('[e2e] Failed to restore journal settings:', error.message);
        }
    };
};

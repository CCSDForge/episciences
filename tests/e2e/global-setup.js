const { seedAndReset } = require('./helpers/app-cli');

/**
 * Runs once before the whole suite: every run starts from the same accounts and from a journal free
 * of what previous runs created. Set E2E_SKIP_SEED=1 to skip it (for instance against an environment
 * where the PHP scripts cannot be run).
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
};

const { execSync } = require('child_process');
const path = require('path');

/**
 * Runs the application's PHP command line scripts from the tests.
 *
 * E2E_PHP_EXEC is the command prefix that runs PHP where the application lives. Locally it goes
 * through the docker-compose PHP container, as the www-data user so that the cache files written by
 * the scripts stay readable by the web server. On CI, where PHP runs on the host, set it to "php".
 */
const DEFAULT_PHP_EXEC = 'docker exec -u www-data -w /var/www/htdocs episciences-php-fpm php';

const projectRoot = path.resolve(__dirname, '..', '..', '..');

/**
 * @param {string} scriptAndArgs script path relative to the project root, followed by its arguments
 * @returns {string} the script output
 */
function runPhp(scriptAndArgs) {
    const phpExec = process.env.E2E_PHP_EXEC || DEFAULT_PHP_EXEC;
    return execSync(`${phpExec} ${scriptAndArgs}`, {
        cwd: projectRoot,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
        timeout: 120000,
    });
}

/**
 * Creates the E2E accounts and removes the papers, volumes and sections left by previous runs.
 * @returns {string}
 */
function seedAndReset() {
    return runPhp('scripts/console.php app:e2e-seed --reset');
}

/**
 * The application queues emails on disk; a cron sends them. Tests flush the queue themselves so that
 * the emails reach Mailpit without waiting for the cron.
 * @returns {string}
 */
function flushMailQueue() {
    return runPhp('scripts/send_mails.php');
}

module.exports = { runPhp, seedAndReset, flushMailQueue };

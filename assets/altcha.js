import 'altcha';
import 'altcha/i18n/europe';

window.$altcha.algorithms.set('ARGON2ID', () => new Worker('/js/vendor/altcha/argon2id.js'));
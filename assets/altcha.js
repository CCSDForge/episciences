import 'altcha';

window.$altcha.algorithms.set('ARGON2ID', () => new Worker('/js/vendor/altcha/argon2id.js'));
module.exports = {
    testEnvironment: 'jsdom',
    setupFilesAfterEnv: ['<rootDir>/tests/js/setup.js'],
    testMatch: ['**/tests/js/**/*.test.js'],
    collectCoverageFrom: [
        'public/js/**/*.js',
        '!public/js/**/*.min.js'
    ],
    coverageDirectory: 'coverage',
    coverageReporters: ['text', 'lcov', 'html'],
    verbose: true,
    transformIgnorePatterns: [
        'node_modules/(?!(@exodus/bytes)/)'
    ]
};
/**
 * For a detailed explanation regarding each configuration property, visit:
 * https://jestjs.io/docs/en/configuration.html
 *
 */
const artifactsPath = 'build/artifacts/jest';

export default {

    // The directory where Jest should store its cached dependency information
    cacheDirectory: '<rootDir>.jestcache',

    // Automatically clear mock calls and instances between every test
    clearMocks: true,

    // Change default test environment from node to jsdom because we are testing a web application.
    // @see https://jestjs.io/docs/configuration#testenvironment-string
    testEnvironment: 'jsdom',

    // The directory where Jest should output its coverage files
    collectCoverage: true,

    coverageDirectory: artifactsPath,

    coverageReporters: [
        'html-spa',
        'text',
        'cobertura',
    ],

    collectCoverageFrom: [
        '(src|lib)/**/*.js',
        '!src/main.js',
    ],

    // Fail testsuite if coverage is below given percentage
    coverageThreshold: {
        global: {
            statements: 100,
            functions: 100,
            branches: 90,
        },
    },

    // Automatically reset mock state between every test
    resetMocks: true,

    // Automatically restore mock state between every test
    restoreMocks: true,

    // This option allows the use of a custom resolver.
    moduleNameMapper: {
        '^src/(.*)$': '<rootDir>/test/storefront/src/$1',
    },

    reporters: [
        'default',
        ['jest-junit', {
            suiteName: 'Shopware 6 Storefront Unit Tests',
            outputDirectory: artifactsPath,
            outputName: 'storefront.junit.xml',
        }],
    ],

    transform: {},

    setupFilesAfterEnv: [
        '<rootDir>/jest.setup.js',
    ],
};

import js from '@eslint/js'
import globals from 'globals';
import esLintPluginJest from 'eslint-plugin-jest';
import esLintPluginStylistic from '@stylistic/eslint-plugin-js';

const isDevMode= process.env.NODE_ENV !== 'production';

export default [
    js.configs.recommended,
    {
        languageOptions: {
            ecmaVersion: 'latest',

            globals: {
                ...globals.browser,
                ...globals.node,
                ...esLintPluginJest.environments.globals.globals,
                AnalyticsGatewayBaseURL: 'readonly',
            },
        },
        plugins: {
            jest: esLintPluginJest,
            '@stylistic/js': esLintPluginStylistic,
        },
        rules: {
            '@stylistic/js/comma-dangle': ['error', 'always-multiline'],
            'one-var': ['error', 'never'],
            'no-console': ['error', { allow: ['warn', 'error'] }],
            'no-debugger': (isDevMode ? 0 : 2),
            'prefer-const': 'warn',
            '@stylistic/js/quotes': ['warn', 'single'],
            '@stylistic/js/indent': ['warn', 4, {
                SwitchCase: 1,
            }],
            '@stylistic/js/space-infix-ops': 'error',
            '@stylistic/js/object-curly-spacing': ['error', 'always'],
            'jest/no-identical-title': 'warn',
            'jest/no-focused-tests': 'error',
            'jest/no-duplicate-hooks': 'error',
        },
    }
];

// eslint.config.js
import js from '@eslint/js'
import cypress from 'eslint-plugin-cypress'
import globals from 'globals'

export default [
  {
    ignores: [
      'assets/bootstrap.min.js',
      'assets/js/vendor/**',
      'assets/js/base/**',
      'vendor/**',
      'public/**',
    ],
  },
  js.configs.recommended,
  {
    plugins: {
      cypress,
    },
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.browser,
        Cypress: 'readonly',
        cy: 'readonly',
        describe: 'readonly',
        it: 'readonly',
        before: 'readonly',
        after: 'readonly',
        beforeEach: 'readonly',
        afterEach: 'readonly',
        context: 'readonly',
        expect: 'readonly',
        assert: 'readonly',
        Turbo: 'readonly',
      },
    },
    rules: {
      semi: 0,
      'no-underscore-dangle': 0,
      'no-param-reassign': 0,
      'class-methods-use-this': 'off',
      'no-restricted-globals': 'off',
      'no-plusplus': 'off',
      'no-alert': 'off',
    },
  },
];

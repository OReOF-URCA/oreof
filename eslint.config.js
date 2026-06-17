// eslint.config.js
import js from '@eslint/js'
import cypress from 'eslint-plugin-cypress'

export default [
  js.configs.recommended,
  {
    plugins: {
      cypress,
    },
    ignores: [
      'assets/js/vendor/',
      'assets/js/base/',
      'vendor/',
    ],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
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
        document: 'readonly',
        fetch: 'readonly',
        confirm: 'readonly',
        window: 'readonly',
        setTimeout: 'readonly',
        clearTimeout: 'readonly',
        setInterval: 'readonly',
        clearInterval: 'readonly',
        localStorage: 'readonly',
        Event: 'readonly',
        URLSearchParams: 'readonly',
        CustomEvent: 'readonly',
        length: 'readonly',
        FormData: 'readonly',
        console: 'readonly',
        Turbo: 'readonly',
        DOMParser: 'readonly',
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

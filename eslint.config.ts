import {globalIgnores} from 'eslint/config'
import {defineConfigWithVueTs, vueTsConfigs} from '@vue/eslint-config-typescript'
import pluginVue from 'eslint-plugin-vue'
import vuetify from 'eslint-plugin-vuetify'
import pluginTs from '@typescript-eslint/eslint-plugin'

export default defineConfigWithVueTs(
  {
    name: 'app/files-to-lint',
    files: ['**/*.{ts,mts,tsx,vue}'],
  },
  globalIgnores(['**/dist/**', '**/dist-ssr/**', '**/coverage/**']),
  pluginVue.configs['flat/recommended'],
  vueTsConfigs.recommended,
  vuetify.configs['flat/recommended'],
  {
    plugins: {
      pluginVue,
      '@typescript-eslint': pluginTs,
    },
    rules: {
      'no-console':
        process.env.NODE_ENV === 'production' ? ['error', {allow: ['error', 'trace']}] : 'off',
      'no-debugger': process.env.NODE_ENV === 'production' ? 'error' : 'off',
      'vue/no-v-text-v-html-on-component': 'off',
      semi: ['error', 'never'],
      'no-unused-vars': 'off',
      'vue/require-explicit-emits': 'off',
      'vue/no-unused-components': process.env.NODE_ENV === 'production' ? 'error' : 'off',
      '@typescript-eslint/no-unused-vars': [
        process.env.NODE_ENV === 'production' ? 'error' : 'off',
        {
          argsIgnorePattern: '^_',
          varsIgnorePattern: '^_',
          caughtErrorsIgnorePattern: '^_',
        },
      ],
      'vue/valid-v-slot': [
        'error',
        {
          allowModifiers: true,
        },
      ],
      'no-prototype-builtins': 'off',
      'vue/attribute-hyphenation': ['error', 'always'],
      'vue/max-attributes-per-line': [
        'error',
        {
          singleline: 1,
          multiline: 1,
        },
      ],
      '@typescript-eslint/no-explicit-any': 'off',
      '@typescript-eslint/ban-ts-comment': 'off',
      'vue/no-v-html': 'off',
    },
  },
)

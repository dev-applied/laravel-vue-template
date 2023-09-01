require("@rushstack/eslint-patch/modern-module-resolution")

module.exports = {
  root: true,
  env: {
    node: true,
  },
  extends: [
    "plugin:vue/recommended",
    "eslint:recommended",
    "@vue/eslint-config-typescript",
  ],
  parserOptions: {
    ecmaVersion: "latest",
  },
  rules: {
    "no-console": process.env.NODE_ENV === "production" ? "error" : "off",
    "no-debugger": process.env.NODE_ENV === "production" ? "error" : "off",
    "vue/no-v-text-v-html-on-component": "off",
    "vue/valid-v-slot": [
      "error",
      {
        allowModifiers: true,
      },
    ],
    "semi": ["error", "never"]
  },
  overrides: [
    {
      files: ['*.ts', '*.vue'],
      rules: {
        'no-undef': 'off',
      },
    },
  ]
}

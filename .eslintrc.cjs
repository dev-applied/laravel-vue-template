require("@rushstack/eslint-patch/modern-module-resolution")

module.exports = {
    root: true,
    env: {
        node: true
    },
    extends: [
        "eslint:recommended",
        "plugin:vue/vue3-strongly-recommended",
        "@vue/eslint-config-typescript",
    ],
    parserOptions: {
        ecmaVersion: "latest"
    },
    rules: {
        "no-console": process.env.NODE_ENV === "production" ? "error" : "off",
        "no-debugger": process.env.NODE_ENV === "production" ? "error" : "off",
        "vue/no-v-text-v-html-on-component": "off",
        "semi": ["error", "never"],
        "no-unused-vars": "off",
        "vue/no-unused-components": process.env.NODE_ENV === "production" ? "error" : "off",
        "@typescript-eslint/no-unused-vars": [
            process.env.NODE_ENV === "production" ? "error" : "off",
            {
                "argsIgnorePattern": "^_",
                "varsIgnorePattern": "^_",
                "caughtErrorsIgnorePattern": "^_"
            }
        ],
        'vue/valid-v-slot': ['error', {
            allowModifiers: true,
        }]
    },
    overrides: [
        {
            files: ["*.ts", "*.vue"],
            rules: {
                "no-undef": "off"
            }
        }
    ]
}

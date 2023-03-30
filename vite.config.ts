import {fileURLToPath, URL} from 'node:url'
import eslint from 'vite-plugin-eslint'
import {defineConfig} from 'vite'
import vue from '@vitejs/plugin-vue2'
import Components from 'unplugin-vue-components/vite'
import laravel from 'laravel-vite-plugin'
import mkcert from 'vite-plugin-mkcert'

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/main.scss', 'resources/ts/main.ts'],
            refresh: true,
        }),
        vue(),
        eslint(),
        mkcert(),
        Components({
            resolvers: [{
                type: "component",
                resolve: (name) => {
                    if (name.match(/^VCurrencyField/)) {
                        return {name, from: "v-currency-field"};
                    }
                    if (name.match(/^V[A-Z]/)) {
                        return {name, from: "vuetify/lib"};
                    }
                }
            }],
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/ts', import.meta.url))
        }
    },
    server: {
        hmr: {
            overlay: true
        }
    },
    css: {
        devSourcemap: true,
        preprocessorOptions: {
            sass: {
                additionalData: [
                    // vuetify variable overrides
                    '@import "resources/scss/_variables.scss"',
                    '',
                ].join('\n'),
            },
        },
    }
})

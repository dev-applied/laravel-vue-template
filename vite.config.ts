import {fileURLToPath, URL} from 'node:url'
// @ts-ignore
import eslint from 'vite-plugin-eslint'
import { defineConfig, loadEnv } from "vite"
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import Vuetify, { transformAssetUrls } from 'vite-plugin-vuetify'
import ViteFonts from 'unplugin-fonts/vite'
import UnheadVite from '@unhead/addons/vite'
import {sentryVitePlugin} from "@sentry/vite-plugin"

// https://vitejs.dev/config/
export default defineConfig(({mode}) => {
  process.env = {...process.env, ...loadEnv(mode, process.cwd())}
  return {
    build: {
      sourcemap: true,
    },
    plugins: [
      laravel({
        input: ['resources/scss/main.scss', 'resources/ts/main.ts'],
        refresh: true,
      }),
      UnheadVite(),
      vue({
        template: {
          transformAssetUrls
        }
      }),
      Vuetify({
        autoImport: { labs: true },
        styles: {
          configFile: 'resources/scss/settings.scss'
        }
      }),
      eslint({
        exclude: [/virtual:/, /node_modules/, /stories/]
      }),
      ViteFonts({
        google: {
          families: [{
            name: 'Roboto',
            styles: 'wght@100;300;400;500;700;900',
          }],
        },
      }),
      sentryVitePlugin({
        bundleSizeOptimizations: {
          excludeDebugStatements: true,
          excludeReplayIframe: true,
        },
        _experiments: {
          injectBuildInformation: true,
        },
        release: {
          create: false,
        },
      }),
    ],
    resolve: {
      alias: {
        '@/scss': fileURLToPath(new URL('./resources/scss', import.meta.url)),
        '@': fileURLToPath(new URL('./resources/ts', import.meta.url)),
      }
    },
    server: {
      host: true,
      port: 8080,
      strictPort: true,
      hmr: {
        protocol: 'wss',
        clientPort: 443,
        port: 8080,
        host: process.env.VITE_HMR_DOMAIN,
        overlay: true,
      }
    },
    css: {
      devSourcemap: true,
      preprocessorOptions: {
        scss: {
          additionalData: [
            // vuetify variable overrides
            '@use "resources/scss/settings";',
            ''
          ].join('\n'),
        },
      },
    }
  }
})

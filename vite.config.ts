import {fileURLToPath, URL} from 'node:url'
// @ts-ignore
import eslint from 'vite-plugin-eslint'
import {defineConfig, loadEnv, type UserConfig} from "vite"
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import Vuetify, {transformAssetUrls} from 'vite-plugin-vuetify'
import UnheadVite from '@unhead/addons/vite'
import {sentryVitePlugin} from "@sentry/vite-plugin"
import {resolve} from "path"
import versioningPlugin from "./resources/ts/plugins/versioning/viteVersioningPlugin"

// https://vitejs.dev/config/
export default defineConfig(({mode, command}) => {
  process.env = {...process.env, ...loadEnv(mode, process.cwd())}
  let options: UserConfig = {
    base: command === 'serve' ? '/hmr' : undefined,
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
        autoImport: {labs: true},
        styles: {
          configFile: fileURLToPath(new URL('./resources/scss/settings.scss', import.meta.url)),
        }
      }),
      eslint({
        exclude: [/virtual:/, /node_modules/, 'resources/ts/types/laravel/**']
      }),
      sentryVitePlugin({
        applicationKey: process.env.VITE_APP_NAME || 'ai-frontend',
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
      versioningPlugin()
    ],
    resolve: {
      alias: {
        '@/scss': fileURLToPath(new URL('./resources/scss', import.meta.url)),
        '@/images': fileURLToPath(new URL('./resources/images', import.meta.url)),
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
        host: process.env.VITE_APP_DOMAIN,
        overlay: true,
      }
    },
    css: {
      devSourcemap: true,
    }
  }

  if (mode === 'capacitor') {
    options = {
      ...options,
      base: '/',
      publicDir: false,
      build: {
        rollupOptions: {
          input: resolve(__dirname, './index.html'),
        },
        outDir: resolve(__dirname, './dist'),
      },
      server: undefined,
    }
  }

  return options
})

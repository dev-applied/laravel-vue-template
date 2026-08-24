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
  const isVitest = !!process.env.VITEST
  let options: UserConfig = {
    base: command === 'serve' ? '/hmr' : undefined,
    build: {
      sourcemap: true,
    },
    plugins: [
      // The Laravel plugin owns public/hot. Under Vitest it loads in non-serve
      // mode and its cleanup hook DELETES public/hot — which silently drops the
      // running dev server back to the stale public/build bundle (the "old
      // dashboard" bug). It isn't needed for jsdom unit tests, so skip it there.
      ...(isVitest ? [] : [laravel({
        input: ['resources/scss/main.scss', 'resources/ts/main.ts'],
        refresh: true,
      })]),
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
      watch: {
        // Use if running into Linux running out of inotify file watchers
        /*usePolling: true,
        interval: 300,*/

        // Also reduce what Vite tries to watch in a Laravel repo.
        ignored: [
          '**/node_modules/**',
          '**/.git/**',
          '**/vendor/**',
          '**/storage/**',
          '**/bootstrap/cache/**',
          '**/public/**',
        ],
      },
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
        sourcemap: true,
        rollupOptions: {
          input: resolve(__dirname, './index.html'),
        },
        outDir:    resolve(__dirname, './dist'),
        emptyOutDir: true,
      },
      // Strip the Laravel + ESLint Vite plugins in capacitor mode — those are
      // dev-server concerns; capacitor mode only builds a static bundle into
      // ./dist for `cap sync` to copy into the native shell.
      plugins: options.plugins?.filter(p => {
        if (!p || typeof p !== 'object' || Array.isArray(p)) return true
        const name = (p as { name?: string }).name
        return name !== 'laravel-vite-plugin' && name !== 'vite-plugin-eslint'
      }),
      server: undefined,
    }
  }

  return options
})

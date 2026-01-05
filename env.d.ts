/// <reference types="vite/client" />

declare const __APP_VERSION__: string

interface ImportMetaEnv {
  VITE_APP_URL: string
  VITE_API_BASE_URL: string
  VITE_PUSHER_APP_KEY: string
  VITE_PUSHER_HOST: string
  VITE_PUSHER_PORT: string
  VITE_PUSHER_SCHEME: string
  VITE_PUSHER_APP_CLUSTER: string
  VITE_APP_NAME: string
  NODE_ENV: 'development' | 'production'
}

/// <reference types="vite/client" />

declare const __APP_VERSION__: string

interface ImportMetaEnv {
  VITE_APP_URL: string
  VITE_API_BASE_URL: string
  /**
   * Absolute API URL the native (Capacitor) bundle calls into. Web reads index
   * from the same origin and uses VITE_API_BASE_URL relative; native is loaded
   * from the app shell (capacitor://) and needs the fully-qualified backend.
   */
  VITE_API_BASE_URL_NATIVE: string
  VITE_PUSHER_APP_KEY: string
  VITE_PUSHER_HOST: string
  VITE_PUSHER_PORT: string
  VITE_PUSHER_SCHEME: string
  VITE_PUSHER_APP_CLUSTER: string
  VITE_APP_NAME: string
  NODE_ENV: 'development' | 'production'
}

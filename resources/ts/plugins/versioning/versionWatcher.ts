// src/versionWatcher.ts
export interface VersionJson {
  version: string;
  builtAt?: string;
}

const CURRENT_VERSION: string =
  typeof __APP_VERSION__ !== 'undefined' ? __APP_VERSION__ : 'dev'

export function getCurrentVersion() {
  return CURRENT_VERSION
}

interface StartVersionPollingOptions {
  /**
   * URL to fetch version info from.
   * Defaults to `/version.json` emitted by the Vite plugin.
   */
  url?: string;
  /*
  * Whether to log version check results to console. Defaults to true.
  */
  logResults?: boolean;
  /**
   * Whether to check immediately on start. Defaults to true.
   */
  checkImmediately?: boolean;
  /**
   * Whether to check when a script or link file fails to load. Defaults to true.
   */
  checkOnLoadFileError?: boolean;
  /**
   * Whether to check when the window gains focus. Defaults to true.
   */
  checkOnWindowFocus?: boolean;
  /**
   * Polling interval in ms.
   * Defaults to 60 seconds.
   */
  intervalMs?: number;
  /**
   * Called when a different version is detected.
   */
  onVersionChange?: (payload: {
    currentVersion: string;
    latestVersion: string;
    info: VersionJson;
  }) => void;
}

/**
 * Start polling the server for a new version.
 * Returns a `stop()` function to cancel polling.
 */
export function startVersionPolling(options: StartVersionPollingOptions = {}) {
  const {
    url = '/version.json',
    checkImmediately = true,
    //logResults = true,
    checkOnLoadFileError = true,
    checkOnWindowFocus = true,
    intervalMs = 60_000,
    onVersionChange,
  } = options

  let timer: number | undefined

  async function checkSystemUpdate() {
    try {
      const res = await fetch(url, { cache: 'no-store' })
      if (!res.ok) return

      const json = (await res.json()) as VersionJson
      const latest = json.version
      // if (logResults) {
      //   // @eslint-disable-next-line no-console
      //   console.warn('[versionWatcher] Current version:', CURRENT_VERSION, 'Latest version:', latest)
      // }

      if (!latest) return

      if (latest !== CURRENT_VERSION && onVersionChange) {
        onVersionChange({
          currentVersion: CURRENT_VERSION,
          latestVersion: latest,
          info: json,
        })
      }
    } catch (_err) {
      // You can hook this into Sentry/logging if you want
      // @eslint-disable-next-line no-console
      //console.warn('[versionWatcher] Failed to fetch version.json', _err)
    }
  }

  function clearTimer() {
    if (timer) {
      window.clearInterval(timer)
      timer = undefined
    }
  }

  if (checkImmediately) {
    checkSystemUpdate().then().catch()
  }
  // then poll
  const pollingCheck = () => {
    if (intervalMs > 0)
      timer = window.setInterval(checkSystemUpdate, intervalMs)
  }
  pollingCheck()

  window.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      pollingCheck()
      if (checkOnWindowFocus)
        checkSystemUpdate().then().catch()
    }
    if (document.visibilityState === 'hidden')
      clearTimer()
  })

  // when page focus, check system update
  window.addEventListener('focus', () => {
    if (checkOnWindowFocus)
      checkSystemUpdate().then().catch()
  })

  if (checkOnLoadFileError) {
    // listener script resource loading error
    window.addEventListener(
      'error',
      (err) => {
        const errTagName = (err?.target as any)?.tagName
        if (errTagName === 'SCRIPT' || errTagName === 'LINK')
          checkSystemUpdate().then().catch()
      },
      true,
    )
  }

  // return a stop function
  return clearTimer
}

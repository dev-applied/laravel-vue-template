import type {Plugin} from 'vite'
import {execSync} from 'node:child_process'
import path from "node:path"
import * as fs from "node:fs"

export default function versioningPlugin(): Plugin {
  let version: string = Date.now().toString()

  try {
    version = execSync('git rev-parse --short HEAD').toString().trim()
  } catch {
    // Fallback to timestamp if git command fails
  }

  const versionJson = JSON.stringify(
    {
      version,
      builtAt: new Date().toISOString(),
    },
    null,
    2
  )

  function writeVersionFile() {
    const publicPath = path.resolve(process.cwd(), 'public', 'version.json')
    fs.writeFileSync(publicPath, versionJson, 'utf-8')
    //console.info(`[versioning-plugin] Wrote ${publicPath}`)
  }

  function isGitIgnored(filePath: string): boolean {
    try {
      const rel = path.relative(process.cwd(), filePath)
      // If file is outside the repo root, don't treat it as ignored
      if (!rel || rel.startsWith('..')) return false
      // Use git to check ignore status; exit code 0 means ignored
      execSync(`git check-ignore -q -- ${JSON.stringify(rel)}`)
      return true
    } catch {
      return false
    }
  }

  return {
    name: 'versioning-plugin',

    // Inject the constant into Vite
    config() {
      return {
        define: {
          __APP_VERSION__: JSON.stringify(version),
        },
      }
    },

    // DEV SERVER (npm run dev)
    configureServer(server) {
      writeVersionFile() // write once at startup

      server.watcher.on('change', (file) => {
        if (isGitIgnored(file)) return
        writeVersionFile() // optional: update version.json when code changes
      })
    },

    // BUILD (npm run build)
    closeBundle() {
      writeVersionFile() // write final version.json
    },
  }
}

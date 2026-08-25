/**
 * The `$file` global this module's plugin.ts registers.
 *
 * A MODULE file (note the `export {}` at the bottom), because augmenting an
 * interface inside an existing module requires one. Declaring
 * '@vue/runtime-core' from a SCRIPT file does the opposite of what it looks
 * like: it declares a NEW ambient module that shadows Vue's own, and every
 * Vue type in the project disappears — 823 errors, measured.
 *
 * The untyped npm packages need the other kind of file entirely, so they live
 * in shims.d.ts next door.
 */

import type {downloadFile, fileUrl, formatFileSize} from "@modules/Files/resources/ts/file"

declare module "@vue/runtime-core" {
  interface ComponentCustomProperties {
    $file: {
      url: typeof fileUrl
      download: typeof downloadFile
      formatFileSize: typeof formatFileSize
    }
  }
}

export {}

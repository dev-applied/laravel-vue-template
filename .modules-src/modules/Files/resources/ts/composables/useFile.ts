import file from "@modules/Files/resources/ts/file"

/**
 * Composition-API access to the same helpers the $file global exposes.
 *
 * The kernel version of this file did `import file from '@/plugins/file'`
 * against a module with no default export, so it always returned undefined.
 * The helper module now has an explicit default export.
 */
export default function useFile() {
  return {file}
}

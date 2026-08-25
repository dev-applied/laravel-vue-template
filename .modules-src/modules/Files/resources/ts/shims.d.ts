/**
 * Ambient declarations for this module's untyped npm dependencies.
 *
 * A SCRIPT file — no top-level import or export anywhere in it. Inside a
 * module, `declare module "mime-matcher"` means *augment the existing module*,
 * which silently does nothing for a package that ships no types; the "Cannot
 * find module" error simply persists with no hint as to why. Declaring a NEW
 * ambient module requires the file not to be a module itself.
 *
 * Both are declared only as far as this module actually uses them. A wrong
 * shape would be worse than a narrow one, because it type-checks.
 */

declare module "mime-matcher" {
  export default class MimeMatcher {
    constructor(...patterns: string[])
    match(mimeType: string): boolean
  }
}

declare module "heic-to" {
  export function heicTo(options: {blob: Blob, type: string, quality?: number}): Promise<Blob>

  export function isHeic(file: File): Promise<boolean>
}

import { onBeforeUnmount, watch, type Ref } from "vue"

export interface UseDocumentTitleOptions {
  /** Suffix appended after the page title, separated by " | ". Defaults to VITE_APP_NAME. */
  suffix?:  string
  /** Separator between the title and suffix. */
  separator?: string
  /** Set to false to skip restoring the previous title on unmount. */
  restore?: boolean
}

/**
 * Set document.title for the lifetime of the calling component.
 *
 *   useDocumentTitle("Edit Item")            // → "Edit Item | App Name"
 *   useDocumentTitle(computed(() => name.value), { suffix: undefined })
 *
 * Accepts either a string (one-shot set) or a Ref/ComputedRef for reactivity.
 * Restores the previous title on unmount unless `restore: false`.
 */
export function useDocumentTitle(
  title: string | Ref<string>,
  options: UseDocumentTitleOptions = {},
): void {
  const suffix    = options.suffix    ?? (import.meta.env.VITE_APP_NAME as string | undefined) ?? ""
  const separator = options.separator ?? " | "
  const restore   = options.restore   ?? true

  const previous = document.title

  function apply(raw: string) {
    document.title = suffix && raw ? `${raw}${separator}${suffix}` : (raw || suffix || previous)
  }

  if (typeof title === "string") {
    apply(title)
  } else {
    watch(title, (v) => apply(v ?? ""), { immediate: true })
  }

  if (restore) {
    onBeforeUnmount(() => { document.title = previous })
  }
}

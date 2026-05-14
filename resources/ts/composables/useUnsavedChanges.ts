import { onBeforeUnmount, onMounted, ref, type Ref } from "vue"
import { onBeforeRouteLeave, type RouteLocationNormalized } from "vue-router"
import { $confirm } from "@/plugins/confirm"

export interface UseUnsavedChangesOptions {
  /** Title shown in the confirm dialog. */
  title?: string
  /** Message shown in the confirm dialog. */
  message?: string
  /**
   * Skip the prompt for routes matching this predicate.
   * Useful for "save and continue" flows that route after a successful submit.
   */
  skipOn?: (to: RouteLocationNormalized) => boolean
}

export interface UseUnsavedChangesReturn {
  isDirty: Ref<boolean>
  markDirty: () => void
  markClean: () => void
}

/**
 * Track unsaved changes on a form/page and warn the user before they navigate away.
 *
 * Registers two guards:
 *   - vue-router `beforeRouteLeave` → opens the project's confirm dialog
 *   - `window.beforeunload` → triggers the browser's native unload warning
 *
 * Usage (Options API):
 *
 *   import {useUnsavedChanges} from "@/composables/useUnsavedChanges"
 *
 *   export default defineComponent({
 *     setup() {
 *       const { isDirty, markDirty, markClean } = useUnsavedChanges({
 *         message: "You have unsaved changes. Leave anyway?"
 *       })
 *       return { isDirty, markDirty, markClean }
 *     },
 *     watch: {
 *       formData: { deep: true, handler() { this.markDirty() } },
 *     },
 *     methods: {
 *       async save() {
 *         await this.$http.post(...)
 *         this.markClean()
 *       },
 *     },
 *   })
 */
export function useUnsavedChanges(options: UseUnsavedChangesOptions = {}): UseUnsavedChangesReturn {
  const isDirty = ref(false)

  const title   = options.title   ?? "Unsaved changes"
  const message = options.message ?? "You have unsaved changes that will be lost. Are you sure you want to leave this page?"

  function markDirty() { isDirty.value = true }
  function markClean() { isDirty.value = false }

  function onBeforeUnload(e: BeforeUnloadEvent) {
    if (!isDirty.value) return
    e.preventDefault()
    // Older browsers required returnValue; modern ones only need preventDefault.
    e.returnValue = ""
  }

  onMounted(() => {
    window.addEventListener("beforeunload", onBeforeUnload)
  })

  onBeforeUnmount(() => {
    window.removeEventListener("beforeunload", onBeforeUnload)
  })

  onBeforeRouteLeave(async (to) => {
    if (!isDirty.value) return true
    if (options.skipOn?.(to)) return true

    const proceed = await $confirm(title, message, "warning", {
      buttonTrueText:  "Leave",
      buttonFalseText: "Stay",
      buttonTrueColor: "error",
    })
    return Boolean(proceed)
  })

  return { isDirty, markDirty, markClean }
}

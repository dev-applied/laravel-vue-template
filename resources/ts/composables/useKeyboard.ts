import { onBeforeUnmount, ref, type Ref } from "vue"
import { Capacitor } from "@capacitor/core"
import { Keyboard, type KeyboardInfo } from "@capacitor/keyboard"

export interface UseKeyboardReturn {
  /** True while the native keyboard is visible (always false on web). */
  isOpen: Ref<boolean>
  /** Keyboard height in pixels — useful for adjusting fixed-position elements. */
  height: Ref<number>
  /** Programmatically dismiss the keyboard. Resolves immediately on web. */
  hide: () => Promise<void>
}

/**
 * Reactive keyboard state for native, no-op refs on web.
 *
 *   const { isOpen, height, hide } = useKeyboard()
 *   // Anchor a submit button above the keyboard:
 *   <v-btn :style="{ marginBottom: isOpen ? `${height}px` : 0 }">
 *
 * The "submit button hidden behind the keyboard" QA finding dies at the
 * source — pages can position critical controls above `height`.
 */
export function useKeyboard(): UseKeyboardReturn {
  const isOpen = ref(false)
  const height = ref(0)

  async function hide(): Promise<void> {
    if (Capacitor.isNativePlatform()) await Keyboard.hide()
  }

  if (!Capacitor.isNativePlatform()) {
    return { isOpen, height, hide }
  }

  const handles: Array<{ remove: () => Promise<void> }> = []
  let unmounted = false

  const track = (h: { remove: () => Promise<void> }): void => {
    if (unmounted) void h.remove()
    else handles.push(h)
  }

  void Keyboard.addListener("keyboardWillShow", (info: KeyboardInfo) => {
    isOpen.value = true
    height.value = info.keyboardHeight
  }).then(track)

  void Keyboard.addListener("keyboardWillHide", () => {
    isOpen.value = false
    height.value = 0
  }).then(track)

  onBeforeUnmount(() => {
    unmounted = true
    handles.forEach(h => { void h.remove() })
  })

  return { isOpen, height, hide }
}

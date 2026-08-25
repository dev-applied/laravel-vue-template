import {defineComponent, type ComponentPublicInstance, type VNode} from "vue"

/**
 * Ported from Vue 2.
 *
 * The previous version subscribed with `this.$on("hook:created")` /
 * `"hook:destroyed"` / `"hook:activated"`. Vue 3 removed BOTH `$on` and the
 * `hook:` event namespace, so every one of those calls would have thrown
 * `this.$on is not a function` the moment a component actually declared a
 * `backButton` option. It never surfaced only because nothing in the template
 * declares one — `beforeCreate` returned early before reaching the first `$on`.
 *
 * It also walked `$children`, which Vue 3 removed as well. The tree walk now
 * goes through the vnode tree, matching plugins/breadcrumbs/mixin.ts — the same
 * plugin shape, already working under Vue 3.
 */

function isFunction(arg: unknown): boolean {
  return typeof arg === "function"
}

type BackButtonState = Pick<BackButton.State, "link" | "text">

function emptyState(): BackButtonState {
  return {link: null, text: null}
}

function triggerUpdate(root: VNode | undefined, backButton: BackButton.Plugin): void {
  const state = getComponentOption(root, emptyState())

  backButton.setLink(state.link)
  backButton.setText(state.text)
}

function getComponentOption(vnode: VNode | undefined, result: BackButtonState): BackButtonState {
  if (!vnode) return result

  if (vnode.component) {
    // The computed this mixin installs is not on ComponentPublicInstance, so it
    // has to be named here. Optional, because the walk visits every component in
    // the tree and most never declared a backButton option.
    const proxy = vnode.component.proxy as
      (ComponentPublicInstance & { $backButtonComputed?: BackButton.Item | null }) | null

    const data = proxy?.$backButtonComputed

    if (data && typeof data === "object") {
      result = {
        link: data.link ?? result.link,
        text: data.text ?? result.text,
      }
    }

    return getComponentOption(vnode.component.subTree, result)
  }

  if (vnode.shapeFlag & 16) {
    // shapeFlag 16 is ARRAY_CHILDREN, so children IS an array here — but the
    // declared type covers every child shape, including null and plain strings.
    const children = (vnode.children ?? []) as VNode[]

    for (const child of children) {
      result = getComponentOption(child, result)
    }
  }

  return result
}

export default function createMixin(backButton: BackButton.Plugin, options: BackButton.Options) {
  return defineComponent({
    computed: {
      $backButtonComputed(): BackButton.Item | null {
        const declared = (this.$options as Record<string, any>)[options.keyName]

        if (!declared) return null

        return isFunction(declared) ? declared.call(this) : declared
      },
    },
    watch: {
      $backButtonComputed: {
        handler() {
          triggerUpdate(this.$root?.$.vnode, backButton)
        },
        immediate: true,
      },
    },
    beforeMount() {
      triggerUpdate(this.$root?.$.vnode, backButton)
    },
    beforeUnmount() {
      triggerUpdate(this.$root?.$.vnode, backButton)
    },
    activated() {
      triggerUpdate(this.$root?.$.vnode, backButton)
    },
    deactivated() {
      triggerUpdate(this.$root?.$.vnode, backButton)
    },
  })
}

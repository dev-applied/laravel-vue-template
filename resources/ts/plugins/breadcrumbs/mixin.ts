import { defineComponent, type ComponentPublicInstance, type VNode } from "vue"

function isFunction(arg: any) {
  return typeof arg === "function"
}

function triggerUpdate(options: any, $root: any, breadCrumbs: Breadcrumbs.Plugin) {
  const state = getComponentOption(options, $root)
  breadCrumbs.setItems(state)
}

function getComponentOption(options: Breadcrumbs.Options, vnode: VNode, result: Breadcrumbs.Item[] = []): Breadcrumbs.Item[] {
  if (!vnode) return result

  if (vnode.component) {
    // The computed this mixin installs is not on ComponentPublicInstance, so it
    // has to be named here. Optional, because the walk visits every component in
    // the tree and most of them never declared a breadCrumbs option.
    const proxy = vnode.component.proxy as
      (ComponentPublicInstance & { $breadCrumbsComputed?: Breadcrumbs.Item[] }) | null

    if (proxy?.$breadCrumbsComputed?.length) {
      result = proxy.$breadCrumbsComputed
    }

    result = getComponentOption(options, vnode.component.subTree, result)
  } else if (vnode.shapeFlag & 16) {
    // shapeFlag 16 is ARRAY_CHILDREN, so children IS an array here — but the
    // declared type covers every child shape, including null and plain strings,
    // and indexing a string yields characters rather than vnodes.
    const vnodes = (vnode.children ?? []) as VNode[]

    for (let i = 0; i < vnodes.length; i++) {
      result = getComponentOption(options, vnodes[i], result)
    }
  }

  return result
}

/**
 * Create mixin
 * @param breadCrumbs
 * @param options
 */
export default function createMixin(breadCrumbs: Breadcrumbs.Plugin, options: { keyName: string }) {
  return defineComponent({
    computed: {
      $breadCrumbsComputed(): Breadcrumbs.Item[] {
          const $keyName = this.$options[options.keyName]
          if (!$keyName) return []
          if (isFunction($keyName)) {
            return $keyName.call(this)
          }
          return $keyName ?? []
        },
    },
    watch: {
      $breadCrumbsComputed: {
        handler() {
          triggerUpdate(options, this.$root?.$.vnode, breadCrumbs)
        },
        immediate: true
      }
    },
    beforeUnmount() {
      triggerUpdate(options, this.$root?.$.vnode, breadCrumbs)
    },
    beforeMount() {
      triggerUpdate(options, this.$root?.$.vnode, breadCrumbs)
    },
    activated() {
      triggerUpdate(options, this.$root?.$.vnode, breadCrumbs)
    },
    deactivated() {
      triggerUpdate(options, this.$root?.$.vnode, breadCrumbs)
    },
  })
}

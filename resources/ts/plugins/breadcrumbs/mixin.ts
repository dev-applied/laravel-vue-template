import { defineComponent, type VNode } from "vue"

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
    if (vnode.component.proxy) {
      if (vnode.component?.proxy.$breadCrumbsComputed.length) {
        result = vnode.component?.proxy.$breadCrumbsComputed
      }
    }
    result = getComponentOption(options, vnode.component.subTree, result)
  } else if (vnode.shapeFlag & 16) {
    const vnodes = vnode.children
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

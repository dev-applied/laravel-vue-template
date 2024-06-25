import type { Component } from "vue"

function isFunction(arg: any) {
  return typeof arg === "function"
}

function triggerUpdate(options: any, $root: any, breadCrumbs: Breadcrumbs.Plugin) {
  const state = getComponentOption(options, $root)
  breadCrumbs.setItems(state)
}

function getComponentOption(options: Breadcrumbs.Options, component: {
  _inactive?: any;
  $breadCrumbsComputed?: any;
  $options?: any;
  $children?: any
}, result: Breadcrumbs.Item[] = []): Breadcrumbs.Item[] {
  if (component._inactive) {
    return result
  }

  const { keyName } = options
  const { $breadCrumbsComputed, $options, $children } = component

  if ($options[keyName] && ((typeof $options[keyName] === "function" && $breadCrumbsComputed) || (typeof $options[keyName] !== "function"))) {
    result = $breadCrumbsComputed || $options[keyName]
  }

  // collect & aggregate child options if deep = true
  if ($children.length) {
    $children.forEach((childComponent: Component) => {
      if (!childComponent || !childComponent.$root) {
        return
      }

      result = getComponentOption(options, childComponent, result)
    })
  }

  return result
}

/**
 * Create mixin
 * @param breadCrumbs
 * @param options
 */
export default function createMixin(breadCrumbs: Breadcrumbs.Plugin, options: { rootKey: string; keyName: string }) {
  const updateOnLifecycleHook = ["activated", "deactivated", "beforeMount"]

  return {
    beforeCreate() {
      // @ts-ignore
      const $root = this[options.rootKey]
      const $options = this.$options
      // @ts-ignore
      const $keyName = $options[options.keyName]

      if (typeof $keyName === "undefined" || $keyName === null) {
        return
      }

      if (isFunction($keyName)) {
        $options.computed = $options.computed || {}
        $options.computed.$breadCrumbsComputed = $keyName


        this.$on("hook:created", () => {
          this.$watch("$breadCrumbsComputed", function() {
            triggerUpdate(options, $root, breadCrumbs)
          })
        })
      }

      this.$on("hook:destroyed", () => {
        this.$nextTick(() => {
          triggerUpdate(options, this.$root, breadCrumbs)
        })
      })

      updateOnLifecycleHook.forEach((lifecycleHook) => {
        this.$on(`hook:${lifecycleHook}`, function() {
          triggerUpdate(options, $root, breadCrumbs)
        })
      })
    }
  }
}

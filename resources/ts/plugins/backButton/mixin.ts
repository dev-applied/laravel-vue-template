import type { DefineComponent } from "vue/types/v3-define-component"

function isFunction({ arg }: { arg: any }) {
  return typeof arg === "function"
}

function triggerUpdate({ options, $root, backButton }: { options: any, $root: any, backButton: any }) {
  const state = getComponentOption(options, $root)
  backButton.setLink(state.link)
  backButton.setText(state.text)
}

function getComponentOption(options: BackButton.Options, component: DefineComponent, result: Pick<BackButton.State, "link" | "text"> = {
  link: null,
  text: null
}): Pick<BackButton.State, "link" | "text"> {

  if (component._inactive) {
    return result
  }

  const { keyName } = options
  const { $backButtonComputed, $options, $children } = component

  if ($options[keyName]) {
    const data = $backButtonComputed || $options[keyName]
    if (typeof data === "object") {
      result = Object.assign(result, data)
    }
  }

  // collect & aggregate child options if deep = true
  if ($children.length) {
    $children.forEach((childComponent: DefineComponent) => {
      if (!childComponent || !childComponent.$root) {
        return
      }

      result = getComponentOption(options, childComponent, result)
    })
  }

  return result
}

export default function createMixin(backButton: BackButton.Plugin, options: BackButton.Options) {
  const updateOnLifecycleHook = ["activated", "deactivated", "beforeMount"]

  return {
    beforeCreate() {
      const $root = this[options.rootKey]
      const $options = this.$options

      if (typeof $options[options.keyName] === "undefined" || $options[options.keyName] === null) {
        return
      }

      if (isFunction({ arg: $options[options.keyName] })) {
        $options.computed = $options.computed || {}
        $options.computed.$backButtonComputed = $options[options.keyName]

        this.$on("hook:created", function() {
          // @ts-ignore
          this.$watch("$backButtonComputed", function() {
            triggerUpdate({ options: options, $root: $root, backButton: backButton })
          })
        })
      }

      this.$on("hook:destroyed", function() {
        // @ts-ignore
        this.$nextTick(() => {
          // @ts-ignore
          triggerUpdate({ options: options, $root: this.$root, backButton: backButton })
        })
      })

      updateOnLifecycleHook.forEach((lifecycleHook) => {
        this.$on(`hook:${lifecycleHook}`, function() {
          triggerUpdate({ options: options, $root: $root, backButton: backButton })
        })
      })
    }
  }
}

<script lang="ts">
import {defineAsyncComponent, defineComponent, markRaw, type PropType} from "vue"

/**
 * Photo evidence for one checklist line.
 *
 * Uploads through the Files module rather than owning an upload path of its
 * own — Checklists has no business storing files, and a project that has
 * already configured a disk, a scanner and a quota should not get a second,
 * unconfigured one because it installed a checklist.
 *
 * `import.meta.glob`, not a static import: modules/Files may not be installed,
 * and a static path to a missing file fails the whole Vite build rather than
 * just this component.
 */
const uploadGlob = import.meta.glob("/modules/Files/resources/ts/components/AppFileUpload.vue")
const uploadPath = "/modules/Files/resources/ts/components/AppFileUpload.vue"

export default defineComponent({
  name: "AppChecklistEvidence",
  props: {
    // `PropType<number | null>`: the value comes off a JSON response where an
    // unanswered item is null, and a bare `Number` prop types as
    // `number | undefined`, which will not accept it.
    modelValue: {type: Number as PropType<number | null>, default: null},
    label: {type: String, default: "Photo"},
    disabled: {type: Boolean, default: false},
  },
  emits: ["update:modelValue"],
  data() {
    return {
      // import.meta.glob hands back a LOADER, not a component: assigning it
      // straight to `:is` renders the literal text "[object Promise]", which is
      // exactly what the page showed. defineAsyncComponent turns the loader
      // into a component; markRaw keeps Vue from deep-proxying the definition.
      uploader: uploadGlob[uploadPath]
        ? markRaw(defineAsyncComponent(uploadGlob[uploadPath] as never))
        : null,
    }
  },
})
</script>

<template>
  <div>
    <component
      :is="uploader"
      v-if="uploader"
      :label="label"
      :disabled="disabled"
      accept="image/*"
      @uploaded="$emit('update:modelValue', $event?.id ?? null)"
    />
    <p
      v-else
      class="text-body-small text-medium-emphasis mb-0"
    >
      Photo evidence needs the Files module. Install it, or choose the
      <code>evidence=none</code> variant of Checklists.
    </p>
  </div>
</template>

<script lang="ts">
import {defineComponent} from "vue"

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
    modelValue: {type: Number, default: null},
    label: {type: String, default: "Photo"},
    disabled: {type: Boolean, default: false},
  },
  emits: ["update:modelValue"],
  data() {
    return {uploader: uploadGlob[uploadPath] ?? null}
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

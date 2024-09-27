<script setup lang="ts">

import {onMounted, ref} from "vue"

const props = defineProps<{
  action: () => Promise<any>
}>()

const loaded = ref(false)
const error = ref(false)
const errorMessage = ref('')

const handleLoad = async () => {
  loaded.value = false
  error.value = false
  errorMessage.value = ''
  try {
    await props.action()
    loaded.value = true
  } catch (e: any) {
    error.value = true
    errorMessage.value = e?.data?.message ?? e?.statusText ?? e
  }
}


onMounted(() => {
  handleLoad()
})
</script>

<template>
  <div v-if="error">
    <slot
      name="error"
      :error="errorMessage"
    >
      <v-empty-state
        action-text="Retry Request"
        image="https://cdn.vuetifyjs.com/docs/images/components/v-empty-state/connection.svg"
        :text="errorMessage || 'There might be a problem with your connection or our servers. Please check your internet connection or try again later. We appreciate your patience.'"
        title="Something Went Wrong"
        @click:action="handleLoad"
      />
    </slot>
  </div>

  <div v-else-if="loaded">
    <slot />
  </div>

  <div v-else>
    <slot name="loading">
      <v-container
        class="fill-height justify-center align-center"
        style="min-height: 300px"
      >
        <v-progress-circular
          :indeterminate="true"
        />
      </v-container>
    </slot>
  </div>
</template>

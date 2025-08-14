<template>
  <app-dialog
    v-model="proxy"
    fullscreen
  >
    <template #activator="{props}">
      <v-hover>
        <template #default="{ isHovering, props: hoverProps }">
          <v-img
            v-if="fileId"
            :src="$file.url(fileId)"
            position="top center"
            style="cursor: pointer"
            v-bind="mergeProps(props, $attrs, hoverProps)"
          >
            <slot>
              <div
                :style="{opacity: isHovering ? 1 : 0}"
                class="d-flex w-100 justify-end"
                style="transition: opacity 0.3s ease"
              >
                <v-icon>mdi-arrow-expand</v-icon>
              </div>
            </slot>
            <template #placeholder>
              <v-row
                align="center"
                class="fill-height ma-0"
                justify="center"
              >
                <v-progress-circular
                  color="black-lighten-5"
                  indeterminate
                />
              </v-row>
            </template>
          </v-img>
        </template>
      </v-hover>
    </template>
    <template #default>
      <v-card
        class="flex flex-column justify-center"
        color="#130f0f"
      >
        <v-card-title>
          <v-spacer />
          <v-icon
            color="white"
            @click="proxy = false"
          >
            mdi-close
          </v-icon>
        </v-card-title>
        <v-card-text
          style="height: calc(100vh - 75px); display: flex; justify-content: center; flex-direction: column;"
        >
          <v-img
            :src="$file.url(fileId)"
            max-height="calc(100vh - 75px)"
          >
            <template #placeholder>
              <v-row
                align="center"
                class="fill-height ma-0"
                justify="center"
              >
                <v-progress-circular
                  color="black-lighten-5"
                  indeterminate
                />
              </v-row>
            </template>
          </v-img>
        </v-card-text>
      </v-card>
    </template>
  </app-dialog>
</template>

<script lang="ts" setup>
import AppDialog from "@/components/AppDialog.vue"
import {useProxiedModel} from "@/composables/useProxy"
import {mergeProps} from "vue"

defineOptions({
  inheritAttrs: false,
})

const model = defineModel<boolean>({
  default: false,
})

const proxy = useProxiedModel(model, false)

withDefaults(defineProps<{
  fileId?: number,
}>(), {
  fileId: undefined,
})
</script>
<style scoped>
</style>

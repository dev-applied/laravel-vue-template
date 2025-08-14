<template>
  <component
    :is="smAndDown ? VBottomSheet : VDialog"
    class="app-dialog"
    v-bind="$attrs"
  >
    <template
      v-for="(_, name) in $slots"
      #[name]="slotData"
    >
      <slot
        v-if="name !== 'default'"
        :name="name"
        v-bind="slotData"
      />
    </template>

    <template #default="{isActive}">
      <slot
        name="default"
        v-bind="{isActive}"
      >
        <v-card>
          <slot name="title">
            <v-card-title class="d-flex justify-space-between align-center">
              <div>{{ title }}</div>
              <v-icon @click="isActive.value = false">
                close
              </v-icon>
            </v-card-title>
          </slot>
          <slot name="body" />
        </v-card>
      </slot>
    </template>
  </component>
</template>

<script lang="ts" setup>
import {VBottomSheet} from "vuetify/components/VBottomSheet"
import {VDialog} from "vuetify/components/VDialog"
import {useDisplay} from "vuetify"
import type {Ref, TemplateRef} from "vue"

defineOptions({
  inheritAttrs: false,
})

type Props =
  {
    title?: string
  }
  & /* @vue-ignore */InstanceType<typeof VBottomSheet>["$props"]
  & /* @vue-ignore */InstanceType<typeof VDialog>["$props"]

defineProps<Props>()

defineSlots<{
  activator(props: {
    isActive: boolean
    props: Record<string, any>
    targetRef: TemplateRef
  }): any,
  default(props: { isActive: Ref<boolean, boolean> }): any
  body(): any,
  title(props: { title: string }): any
}>()

const {smAndDown} = useDisplay()
</script>


<style lang="scss">
.v-overlay-container .v-dialog.v-bottom-sheet.app-dialog > .v-overlay__content {
  max-width: 100% !important;
}
</style>

<template>
  <v-dialog
    eager
    @update:model-value="change"
    :model-value="true"
    :max-width="width"
    :persistent="persistent"
    @keydown.esc="choose(undefined)"
  >
    <v-card tile>
      <v-toolbar
        v-if="!!title"
        dark
        :color="color"
        dense
        flat
      >
        <v-icon
          v-if="!!icon"
          class="ml-4"
        >
          {{ icon }}
        </v-icon>
        <v-toolbar-title
          class="white--text"
          v-text="title"
        />
        <v-spacer />
        <v-icon
          v-if="showCancel"
          icon="mdi-close"
          @click="choose(undefined)"
          class="mr-4"
        />
      </v-toolbar>
      <v-card-text
        class="body-1 text-body-1 py-3"
        v-html="message"
      />
      <v-card-actions>
        <v-spacer />
        <v-btn
          v-if="Boolean(buttonFalseText)"
          :color="buttonFalseColor"
          :variant="buttonFalseFlat ? 'text' : undefined"
          @click="choose(false)"
        >
          {{ buttonFalseText }}
        </v-btn>
        <v-btn
          v-if="Boolean(buttonTrueText)"
          :color="buttonTrueColor"
          :variant="buttonTrueFlat ? 'text' : undefined"
          @click="choose(true)"
        >
          {{ buttonTrueText }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script lang="ts">

export default {
  emits: ['close'],
  props: {
    buttonTrueText: {
      type: String,
      default: 'Confirm'
    },
    buttonFalseText: {
      type: String,
      default: 'Cancel'
    },
    buttonTrueColor: {
      type: String,
      default: 'primary'
    },
    buttonFalseColor: {
      type: String,
      default: 'grey'
    },
    buttonFalseFlat: {
      type: Boolean,
      default: true
    },
    buttonTrueFlat: {
      type: Boolean,
      default: true
    },
    color: {
      type: String,
      default: 'warning'
    },
    icon: {
      type: String,
      default: 'mdi-alert'
    },
    message: {
      type: String,
      required: true
    },
    persistent: Boolean,
    title: {
      type: String,
      default: undefined
    },
    width: {
      type: Number,
      default: 450
    },
    showCancel: {
      type: Boolean,
      default: false
    }
  },
  mounted () {
    document.addEventListener('keyup', this.onEnterPressed)
  },
  unmounted () {
    document.removeEventListener('keyup', this.onEnterPressed)
  },
  methods: {
    onEnterPressed (e) {
      if (e.keyCode === 13) {
        e.stopPropagation()
        this.choose(true)
      }
    },
    choose (value) {
      this.$emit('close', value)
    },
    change () {
      this.$emit('close', false)
    }
  }
}
</script>
<style scoped lang="scss">
.v-dialog .v-toolbar-title {
  min-width: calc(100% - 160px);
}
</style>

<template>
  <div
    :class="classes"
    :style="styles"
    class="load-more"
    @click="click"
  />
</template>

<script lang="ts">
import {defineComponent} from 'vue'

export default defineComponent({
  props: {
    left: {
      type: Boolean,
      default: false
    },
    loading: {
      type: Boolean,
      default: false
    },
    color: {
      type: String,
      default: 'black'
    }
  },
  computed: {
    classes() {
      if (this.loading) {
        return 'active'
      }
      return ''
    },
    styles() {
      return {
        '--rotate': this.left ? '-225deg' : '-45deg',
        '--color': this.color
      }
    }
  },
  methods: {
    click() {
      this.$emit('click')
    }
  }
})
</script>

<style lang="scss" scoped>
@keyframes rotate {
  to {
    transform: rotate(calc(var(--rotate) + 360deg));
  }
}

@keyframes rotate2 {
  to {
    transform: rotate(calc(var(--rotate) - 360deg));
  }
}

.load-more {
  --color: black;
  --rotate: -45deg;

  display: block;
  cursor: pointer;
  width: 43px;
  height: 36px;
  transform: translate(-50% -50%);

  &:before,
  &:after {
    display: block;
    content: "";
    width: 12px;
    height: 12px;
    border-width: 2px;
    border-style: solid;
    border-color: transparent;
    border-bottom-color: var(--color);
    border-right-color: var(--color);
    transform: rotate(var(--rotate));
    transition: all 0.5s ease-in-out;
  }

  &:before {
    margin: 14px auto 0;
  }

  &:after {
    margin: -12px auto 0;
  }

  &.active {
    &:before {
      border-width: 1px;
      border-radius: 50%;
      animation: 0.8s rotate 0.4s linear forwards infinite;
    }

    &:after {
      border-width: 1px;
      margin-top: -16px;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      animation: 0.8s rotate2 0.4s linear forwards infinite;
    }
  }

  &.disabled {
    cursor: no-drop;

    &:before,
    &:after {
      border-bottom-color: #c5c5c5;
      border-right-color: #c5c5c5;
    }
  }
}
</style>

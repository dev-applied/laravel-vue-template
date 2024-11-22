<template>
  <div class="password-condition-container">
    <div
      v-for="index in [0,1,2,3,4,5]"
      :key="index"
      :class="{'satisfied': conditions[index].data}"
      class="text-left"
    >
      <v-badge
        class="password-condition"
        color="#02d9c1"
        dot
        inline
      />
      <span
        class="ml-2 text-body"
      >
        {{ conditions[index].text }}
      </span>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import validators from "@/mixins/validators"

export default defineComponent({
  mixins: [validators],
  props: {
    password: {
      type: String as PropType<string | null>,
      default: null
    }
  },
  computed: {
    conditions() {
      return [
        { text: '8+ characters', data: this.rules.hasNumberOfChars(this.password) },
        { text: 'at least 1 capital letter', data: this.rules.hasUppercase(this.password) },
        { text: 'at least 1 lowercase letter', data: this.rules.hasLowercase(this.password) },
        { text: 'at least 1 number', data: this.rules.hasNumber(this.password) },
        { text: 'a special character such as #,!,@,$,%,^,&,*,(,),-,+,=.', data: this.rules.hasSymbol(this.password) },
        { text: 'no spaces', data: this.rules.hasSpace(this.password) }
      ]
    },
  }
})
</script>

<style scoped lang="scss">
.password-condition-container {
  margin-top: 20px;
  display: flex;
  flex-wrap: wrap;

  > * {
    font-size: 11px;
    flex: 0 0 50%;
    padding: 2px 10px;
  }

  :deep() {
    .password-condition {
      .v-badge__badge {
        background: linear-gradient(168.24deg, #FD5F76 51.64%, #FE805A 100%) !important;
      }
    }

    .satisfied {
      .password-condition {
        .v-badge__badge {
          background: #5DFDCB !important;
        }
      }
    }
  }
}
</style>

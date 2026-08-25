<script lang="ts">
import {defineComponent, type PropType} from "vue"
import {mentionToken} from "@modules/Comments/resources/ts/composables/useComments"

interface MentionableUser {id: number, name: string}

/**
 * Textarea with an @mention picker. Typing `@` opens a menu; picking someone
 * inserts the explicit token the backend parses.
 */
export default defineComponent({
  name: "AppCommentComposer",
  props: {
    modelValue:  {type: String, default: ""},
    /** Candidates for @mention. Empty disables the picker entirely. */
    mentionable: {type: Array as PropType<MentionableUser[]>, default: () => []},
    placeholder: {type: String, default: "Write a comment…"},
    /** Offer the internal-note switch. Set from the viewer's ability. */
    allowInternal: {type: Boolean, default: false},
    loading:     {type: Boolean, default: false},
    submitLabel: {type: String, default: "Comment"},
  },
  emits: ['update:modelValue', 'submit'],
  data() {
    return {
      isInternal: false,
      menu: false,
      query: "",
      // Where the `@` that opened the menu sits, so the token replaces it.
      anchor: -1,
    }
  },
  computed: {
    matches(): MentionableUser[] {
      const q = this.query.toLowerCase()
      return this.mentionable
        .filter((u) => !q || u.name.toLowerCase().includes(q))
        .slice(0, 8)
    },
  },
  methods: {
    onInput(value: string) {
      this.$emit('update:modelValue', value)
      if (!this.mentionable.length) return

      // Look back from the caret for an unbroken `@word`.
      const caret = (this.$refs.input as HTMLTextAreaElement | undefined)?.selectionStart ?? value.length
      const upto  = value.slice(0, caret)
      const match = /@([\w'-]*)$/.exec(upto)

      if (match) {
        this.anchor = caret - match[0].length
        this.query  = match[1]
        this.menu   = true
      } else {
        this.menu = false
        this.anchor = -1
      }
    },
    pick(user: MentionableUser) {
      if (this.anchor < 0) return

      const caret = (this.$refs.input as HTMLTextAreaElement | undefined)?.selectionStart ?? this.modelValue.length
      const next  = this.modelValue.slice(0, this.anchor) + mentionToken(user) + ' ' + this.modelValue.slice(caret)

      this.$emit('update:modelValue', next)
      this.menu = false
      this.anchor = -1
    },
    submit() {
      if (!this.modelValue.trim()) return
      this.$emit('submit', {body: this.modelValue, isInternal: this.isInternal})
    },
  },
})
</script>

<template>
  <div class="app-comment-composer">
    <v-menu
      v-model="menu"
      :close-on-content-click="false"
      location="bottom start"
      :open-on-click="false"
    >
      <template #activator="{props: menuProps}">
        <v-textarea
          v-bind="menuProps"
          ref="input"
          auto-grow
          density="comfortable"
          hide-details
          :model-value="modelValue"
          :placeholder="placeholder"
          rows="2"
          variant="outlined"
          @update:model-value="onInput"
        />
      </template>

      <v-list
        v-if="matches.length"
        density="compact"
        max-width="280"
      >
        <v-list-item
          v-for="user in matches"
          :key="user.id"
          :title="user.name"
          @click="pick(user)"
        />
      </v-list>
    </v-menu>

    <div class="d-flex align-center flex-wrap ga-2 mt-2">
      <v-switch
        v-if="allowInternal"
        v-model="isInternal"
        color="warning"
        density="compact"
        hide-details
        label="Internal note"
      />
      <v-spacer />
      <v-btn
        color="primary"
        :disabled="!modelValue.trim()"
        :loading="loading"
        variant="flat"
        @click="submit"
      >
        {{ submitLabel }}
      </v-btn>
    </div>
  </div>
</template>

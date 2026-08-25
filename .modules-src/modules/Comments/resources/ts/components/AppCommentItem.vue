<script lang="ts">
import {defineComponent, type PropType} from "vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"
import {toPlainText, type Comment} from "@modules/Comments/resources/ts/composables/useComments"

export default defineComponent({
  name: "AppCommentItem",
  components: {AppTimeAgo},
  props: {
    comment:     {type: Object as PropType<Comment>, required: true},
    allowReply:  {type: Boolean, default: false},
  },
  emits: ['reply', 'edit', 'delete'],
  data() {
    return {
      editing: false,
      draft:   "",
    }
  },
  computed: {
    display(): string {
      return toPlainText(this.comment.body)
    },
    initials(): string {
      const name = this.comment.author?.name ?? "?"
      return name.split(/\s+/).map((p) => p[0]).slice(0, 2).join("").toUpperCase()
    },
  },
  methods: {
    startEdit() {
      this.draft = this.comment.body
      this.editing = true
    },
    save() {
      if (!this.draft.trim()) return
      this.$emit('edit', this.comment, this.draft)
      this.editing = false
    },
  },
})
</script>

<template>
  <div class="app-comment-item d-flex ga-3 py-3">
    <v-avatar
      :color="comment.isInternal ? 'warning' : 'primary'"
      size="36"
      variant="tonal"
    >
      <span class="text-body-small">{{ initials }}</span>
    </v-avatar>

    <div class="flex-grow-1 min-width-0">
      <div class="d-flex align-center ga-2 flex-wrap">
        <span class="text-body-medium font-weight-medium">{{ comment.author?.name ?? 'Unknown' }}</span>
        <span class="text-body-small text-medium-emphasis">
          <AppTimeAgo :value="comment.createdAt" />
        </span>
        <!-- A comment that silently changed after someone replied to it is
             worse than one that says it changed. -->
        <span
          v-if="comment.editedAt"
          class="text-body-small text-disabled"
        >edited</span>
        <v-chip
          v-if="comment.isInternal"
          color="warning"
          density="comfortable"
          size="x-small"
          variant="tonal"
        >
          Internal
        </v-chip>
      </div>

      <!-- v-show, not v-if: swapping the node would drop focus mid-edit. -->
      <div
        v-show="!editing"
        class="text-body-medium text-pre-wrap mt-1"
      >
        {{ display }}
      </div>

      <div
        v-show="editing"
        class="mt-2"
      >
        <v-textarea
          v-model="draft"
          auto-grow
          density="compact"
          hide-details
          rows="2"
          variant="outlined"
        />
        <div class="d-flex ga-2 mt-2">
          <v-btn
            color="primary"
            size="small"
            variant="flat"
            @click="save"
          >
            Save
          </v-btn>
          <v-btn
            size="small"
            variant="text"
            @click="editing = false"
          >
            Cancel
          </v-btn>
        </div>
      </div>

      <div
        v-show="!editing"
        class="d-flex ga-1 mt-1"
      >
        <v-btn
          v-if="allowReply && comment.parentId === null"
          size="x-small"
          variant="text"
          @click="$emit('reply', comment)"
        >
          Reply
        </v-btn>
        <!-- Only offered when canEdit — never offer and then 403. -->
        <v-btn
          v-if="comment.canEdit"
          size="x-small"
          variant="text"
          @click="startEdit"
        >
          Edit
        </v-btn>
        <v-btn
          v-if="comment.canEdit"
          color="error"
          size="x-small"
          variant="text"
          @click="$emit('delete', comment)"
        >
          Delete
        </v-btn>
      </div>

      <div
        v-if="comment.replies?.length"
        class="ms-4 mt-1 border-s ps-3"
      >
        <AppCommentItem
          v-for="reply in comment.replies"
          :key="reply.id"
          :comment="reply"
          @delete="$emit('delete', $event)"
          @edit="(c, body) => $emit('edit', c, body)"
        />
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.min-width-0 {
  min-width: 0;
}
</style>

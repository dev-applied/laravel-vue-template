<script lang="ts">
import {defineComponent, type PropType} from "vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import AppCommentItem from "@modules/Comments/resources/ts/components/AppCommentItem.vue"
import AppCommentComposer from "@modules/Comments/resources/ts/components/AppCommentComposer.vue"
import useComments, {type Comment} from "@modules/Comments/resources/ts/composables/useComments"

/**
 * The whole comment surface for one record. Registered globally.
 *
 *   <AppComments type="order" :record-id="order.id" :mentionable="teammates" />
 */
export default defineComponent({
  name: "AppComments",
  components: {AppEmptyState, AppCommentItem, AppCommentComposer},
  props: {
    /** The alias the project registered in CommentableRegistry. */
    type:        {type: String, required: true},
    recordId:    {type: [Number, String], required: true},
    mentionable: {type: Array as PropType<{id: number, name: string}[]>, default: () => []},
    /** Set from the viewer's `see-internal-comments` ability. */
    allowInternal: {type: Boolean, default: false},
    /** Matches the module's `threading` option. */
    threaded:    {type: Boolean, default: false},
    title:       {type: String, default: "Comments"},
  },
  setup(props) {
    return useComments(props.type, props.recordId)
  },
  data() {
    return {
      draft:     "",
      posting:   false,
      replyTo:   null as Comment | null,
    }
  },
  mounted() {
    this.fetch()
  },
  methods: {
    async submit({body, isInternal}: {body: string, isInternal: boolean}) {
      this.posting = true
      const ok = await this.post(body, {isInternal, parentId: this.replyTo?.id ?? null})
      this.posting = false

      if (!ok) return   // the error is already surfaced; keep what they typed

      this.draft = ""
      this.replyTo = null
    },
    async onDelete(comment: Comment) {
      const extra = comment.replies?.length ? " Its replies go with it." : ""
      if (!await this.$confirm("Delete comment?", `This cannot be undone.${extra}`)) return

      await this.remove(comment)
    },
  },
})
</script>

<template>
  <v-card class="app-comments">
    <v-card-title class="d-flex align-center ga-2">
      <span>{{ title }}</span>
      <v-chip
        v-if="count"
        density="comfortable"
        size="small"
      >
        {{ count }}
      </v-chip>
      <v-spacer />
      <v-progress-circular
        v-show="loading"
        indeterminate
        size="18"
        width="2"
      />
    </v-card-title>

    <v-divider />

    <v-card-text>
      <div v-if="comments.length">
        <AppCommentItem
          v-for="comment in comments"
          :key="comment.id"
          :allow-reply="threaded"
          :comment="comment"
          @delete="onDelete"
          @edit="edit"
          @reply="replyTo = $event"
        />
      </div>

      <AppEmptyState
        v-else-if="loaded"
        icon="chat_bubble_outline"
        description="Start the conversation."
        title="No comments yet"
      />
    </v-card-text>

    <v-divider />

    <v-card-text>
      <v-alert
        v-if="replyTo"
        class="mb-2"
        density="compact"
        type="info"
        variant="tonal"
      >
        Replying to {{ replyTo.author?.name ?? 'a comment' }}
        <template #append>
          <v-btn
            icon="close"
            aria-label="Cancel this reply"
            size="x-small"
            variant="text"
            @click="replyTo = null"
          />
        </template>
      </v-alert>

      <AppCommentComposer
        v-model="draft"
        :allow-internal="allowInternal"
        :loading="posting"
        :mentionable="mentionable"
        :submit-label="replyTo ? 'Reply' : 'Comment'"
        @submit="submit"
      />
    </v-card-text>
  </v-card>
</template>

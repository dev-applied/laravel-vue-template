import {computed, ref, shallowRef} from "vue"
import useHttp from "@/composables/useHttp"

export interface CommentAuthor {
  id:   number | null
  name: string
}

export interface CommentMention {
  id:   number
  name: string
}

export interface Comment {
  id:         number
  body:       string
  isInternal: boolean
  parentId:   number | null
  author?:    CommentAuthor
  mentions?:  CommentMention[]
  replies?:   Comment[]
  /** False for someone else's comment — the UI omits edit/delete entirely. */
  canEdit:    boolean
  editedAt?:  string | null
  createdAt?: string | null
}

/** The token a mention is stored as. The id is explicit so two people sharing
 *  a first name can never be confused for each other. */
export function mentionToken(user: {id: number, name: string}): string {
  return `@[${user.name}](user:${user.id})`
}

/** Mention markup flattened to plain names, for display. */
export function toPlainText(body: string): string {
  return body.replace(/@\[([^\]]{1,120})\]\(user:\d+\)/g, '@$1')
}

/**
 * Comments on one record.
 *
 *   const comments = useComments('order', order.id)
 */
export default function useComments(type: string, id: number | string) {
  const {$http, $error} = useHttp()

  const comments = shallowRef<Comment[]>([])
  const loading  = ref(false)
  const loaded   = ref(false)

  const base = `/comments/${type}/${id}`

  const count = computed(() =>
    comments.value.reduce((total, c) => total + 1 + (c.replies?.length ?? 0), 0)
  )

  async function fetch(): Promise<void> {
    loading.value = true

    const response = await $http.get(base).catch((e: any) => e)

    loading.value = false
    if ($error(response.status, response.data?.message, response.data?.errors)) return

    comments.value = response.data.comments
    loaded.value = true
  }

  async function post(body: string, options: {isInternal?: boolean, parentId?: number | null} = {}): Promise<boolean> {
    const response = await $http.post(base, {
      body,
      is_internal: options.isInternal ?? false,
      parent_id:   options.parentId ?? null,
    }).catch((e: any) => e)

    if ($error(response.status, response.data?.message, response.data?.errors)) return false

    // Refetch rather than push: a reply has to land inside its parent, and the
    // server is the one that decided whether it stayed a reply at all.
    await fetch()

    return true
  }

  async function edit(comment: Comment, body: string): Promise<boolean> {
    const response = await $http.put(`/comments/${comment.id}`, {body}).catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors)) return false

    await fetch()

    return true
  }

  async function remove(comment: Comment): Promise<boolean> {
    const response = await $http.delete(`/comments/${comment.id}`).catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors)) return false

    await fetch()

    return true
  }

  return {comments, count, loading, loaded, fetch, post, edit, remove}
}

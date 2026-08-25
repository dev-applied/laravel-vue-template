<script lang="ts">
import {defineComponent} from "vue"
import {useGlobalSearch} from "@modules/GlobalSearch/resources/ts/composables/useGlobalSearch"

interface SearchResult {
  id: number | string
  type: string
  typeLabel: string
  icon: string | null
  title: string
  subtitle: string | null
  route: {name: string, params?: Record<string, unknown>} | null
}

interface SearchGroup {
  type: string
  label: string
  icon: string | null
  hasMore: boolean
  results: SearchResult[]
}

interface RecentSearch { id: number, term: string, resultCount: number }

/**
 * Command palette. Mount ONCE, in the layout:
 *
 *   <AppGlobalSearch />
 *
 * and open it from anywhere with `useGlobalSearch().openSearch()`, or Cmd/Ctrl+K.
 */
export default defineComponent({
  name: "AppGlobalSearch",
  setup() {
    return useGlobalSearch()
  },
  data() {
    return {
      query: "",
      groups: [] as SearchGroup[],
      recents: [] as RecentSearch[],
      // Tri-state on purpose. `null` means "we have not asked yet" and is what
      // keeps the recents section from flashing empty on first open; `false`
      // means the history variant is not installed and the section never shows.
      historyAvailable: null as boolean | null,
      loading: false,
      cursor: 0,
      debounce: undefined as ReturnType<typeof setTimeout> | undefined,
      // Every request carries a sequence number and a late one is discarded.
      // Without it, typing "inv" then "invoice" can paint the "inv" results
      // last, and the list disagrees with the box the user is looking at.
      sequence: 0,
    }
  },
  computed: {
    flat(): SearchResult[] {
      return this.groups.flatMap(group => group.results)
    },
    total(): number {
      return this.flat.length
    },
    showRecents(): boolean {
      return this.query.trim().length < 2 && this.historyAvailable === true && this.recents.length > 0
    },
    shortcutLabel(): string {
      const mac = typeof navigator !== "undefined" && /Mac|iPhone|iPad/.test(navigator.platform)
      return mac ? "⌘K" : "Ctrl+K"
    },
  },
  watch: {
    open(isOpen: boolean) {
      if (!isOpen) return

      this.query = this.initialQuery
      this.cursor = 0
      this.groups = []
      void this.loadRecents()

      if (this.query.trim().length >= 2) this.run()

      void this.$nextTick(() => {
        const field = this.$refs.field as {focus?: () => void} | undefined
        field?.focus?.()
      })
    },
    query() {
      this.cursor = 0
      clearTimeout(this.debounce)
      // 250ms: long enough that a normal typing burst is one request, short
      // enough that the pause before you look at the screen is the pause the
      // results arrive in.
      this.debounce = setTimeout(() => this.run(), 250)
    },
  },
  mounted() {
    window.addEventListener("keydown", this.onGlobalKey)
  },
  beforeUnmount() {
    window.removeEventListener("keydown", this.onGlobalKey)
    clearTimeout(this.debounce)
  },
  methods: {
    onGlobalKey(event: KeyboardEvent) {
      if (event.key?.toLowerCase() === "k" && (event.metaKey || event.ctrlKey)) {
        event.preventDefault()
        this.openSearch()
      }
    },
    async run() {
      const term = this.query.trim()

      if (term.length < 2) {
        this.groups = []
        this.loading = false
        return
      }

      const mine = ++this.sequence
      this.loading = true

      const response = await this.$http.get("/search", {params: {q: term, limit: 5}}).catch(e => e)

      if (mine !== this.sequence) return

      this.loading = false

      if (this.$error(response.status, response.data?.message)) return

      this.groups = response.data.data.groups
      this.cursor = 0
    },
    async loadRecents() {
      if (this.historyAvailable === false) return

      const response = await this.$http.get("/search/history", {params: {limit: 6}}).catch(e => e)

      // A 404 here is the `history=none` install, not a fault: the routes file
      // is dropped with the table. Anything else is a real failure and is
      // surfaced rather than swallowed.
      if (response.status === 404) {
        this.historyAvailable = false
        return
      }

      if (this.$error(response.status, response.data?.message)) return

      this.historyAvailable = true
      this.recents = response.data.data
    },
    move(delta: number) {
      if (this.total === 0) return
      this.cursor = (this.cursor + delta + this.total) % this.total
      void this.$nextTick(() => {
        const el = document.getElementById(`global-search-result-${this.cursor}`)
        el?.scrollIntoView({block: "nearest"})
      })
    },
    choose(result?: SearchResult) {
      const target = result ?? this.flat[this.cursor]
      if (!target) return

      void this.remember()
      this.closeSearch()

      if (target.route?.name) {
        void this.$router.push(this.$routeTo(target.route.name, target.route.params ?? {}))
      }
    },
    async remember() {
      if (this.historyAvailable !== true) return

      const term = this.query.trim()
      if (term.length < 2) return

      // Recorded on CHOOSE, not on keystroke — a palette searches as you type,
      // so recording every request would file i, in, inv, invo… and the recent
      // list would show one word spelled out.
      await this.$http.post("/search/history", {term, result_count: this.total}).catch(e => e)
    },
    useRecent(entry: RecentSearch) {
      this.query = entry.term
    },
    async clearRecents() {
      const response = await this.$http.delete("/search/history").catch(e => e)
      if (this.$error(response.status, response.data?.message)) return
      this.recents = []
    },
    indexOf(group: SearchGroup, result: SearchResult): number {
      return this.flat.findIndex(candidate => candidate.type === group.type && candidate.id === result.id)
    },
  },
})
</script>

<template>
  <v-dialog
    :model-value="open"
    max-width="640"
    scrollable
    @update:model-value="$event ? openSearch(query) : closeSearch()"
  >
    <v-card>
      <v-card-text class="pb-0">
        <v-text-field
          ref="field"
          v-model="query"
          autofocus
          clearable
          density="comfortable"
          hide-details
          placeholder="Search everything…"
          prepend-inner-icon="search"
          variant="solo-filled"
          flat
          aria-label="Search everything"
          role="combobox"
          aria-controls="global-search-results"
          :aria-expanded="total > 0"
          @keydown.down.prevent="move(1)"
          @keydown.up.prevent="move(-1)"
          @keydown.enter.prevent="choose()"
          @keydown.esc="closeSearch()"
        >
          <template #append-inner>
            <span class="text-body-small text-medium-emphasis">{{ shortcutLabel }}</span>
          </template>
        </v-text-field>
      </v-card-text>

      <v-progress-linear
        :active="loading"
        indeterminate
        color="primary"
      />

      <v-card-text
        id="global-search-results"
        style="max-height: 60vh"
        role="listbox"
        aria-label="Search results"
      >
        <v-list
          v-show="total > 0"
          density="comfortable"
        >
          <template
            v-for="group in groups"
            :key="group.type"
          >
            <v-list-subheader class="text-label-medium">
              {{ group.label }}
            </v-list-subheader>
            <v-list-item
              v-for="result in group.results"
              :id="`global-search-result-${indexOf(group, result)}`"
              :key="`${result.type}-${result.id}`"
              :active="indexOf(group, result) === cursor"
              :prepend-icon="result.icon ?? group.icon ?? 'chevron_right'"
              role="option"
              :aria-selected="indexOf(group, result) === cursor"
              @click="choose(result)"
            >
              <v-list-item-title>{{ result.title }}</v-list-item-title>
              <v-list-item-subtitle v-if="result.subtitle">
                {{ result.subtitle }}
              </v-list-item-subtitle>
            </v-list-item>
            <v-list-item
              v-if="group.hasMore"
              class="text-body-small text-medium-emphasis"
              :title="`More ${group.label.toLowerCase()} match — keep typing to narrow`"
            />
          </template>
        </v-list>

        <div v-show="showRecents && total === 0">
          <div class="d-flex align-center">
            <span class="text-label-medium text-medium-emphasis">Recent searches</span>
            <v-spacer />
            <v-btn
              size="small"
              variant="text"
              @click="clearRecents"
            >
              Clear
            </v-btn>
          </div>
          <v-list density="comfortable">
            <v-list-item
              v-for="entry in recents"
              :key="entry.id"
              prepend-icon="history"
              :title="entry.term"
              @click="useRecent(entry)"
            />
          </v-list>
        </div>

        <p
          v-show="!loading && total === 0 && query.trim().length >= 2"
          class="text-body-medium text-medium-emphasis text-center py-6 mb-0"
          role="status"
          aria-live="polite"
        >
          Nothing matched “{{ query.trim() }}”.
        </p>

        <p
          v-show="!loading && total === 0 && query.trim().length < 2 && !showRecents"
          class="text-body-medium text-medium-emphasis text-center py-6 mb-0"
        >
          Type at least two characters.
        </p>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

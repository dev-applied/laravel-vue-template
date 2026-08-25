<script lang="ts">
import {defineComponent, type PropType} from "vue"
import AppTextField from "@/components/fields/AppTextField.vue"
import useSavedViews, {type SavedView} from "@modules/SavedViews/resources/ts/composables/useSavedViews"

/**
 * Drops next to any listing. Give it the screen key and the current state; it
 * emits `apply` with a previously saved state.
 */
export default defineComponent({
  name: "AppSavedViews",
  components: {AppTextField},
  props: {
    /** Screen identifier, e.g. "items.index". Views never cross keys. */
    viewKey: {type: String, required: true},
    /** Whatever the screen wants saved — filters, sort, columns. */
    current: {type: Object as PropType<Record<string, unknown>>, required: true},
    /** Offer the "share with the team" switch. */
    allowSharing: {type: Boolean, default: true},
    /** Apply the user's default view on mount. */
    applyDefault: {type: Boolean, default: true},
  },
  emits: ['apply'],
  setup(props) {
    return useSavedViews(props.viewKey)
  },
  data() {
    return {
      activeId: null as number | null,
      dialog:   false,
      name:     "",
      shareIt:  false,
      makeDefault: false,
      saving:   false,
    }
  },
  computed: {
    activeName(): string {
      return this.views.find((v: SavedView) => v.id === this.activeId)?.name ?? "All records"
    },
  },
  async mounted() {
    await this.fetch()

    // Applying the default is opt-out: a person who set one expects the screen
    // to open on it, and re-picking it every visit is the complaint that makes
    // saved views feel pointless.
    if (this.applyDefault && this.defaultView) this.apply(this.defaultView)
  },
  methods: {
    apply(view: SavedView) {
      this.activeId = view.id
      this.$emit('apply', view.payload)
    },
    clear() {
      this.activeId = null
      this.$emit('apply', null)
    },
    openSave() {
      this.name = ""
      this.shareIt = false
      this.makeDefault = false
      this.dialog = true
    },
    async submit() {
      if (!this.name.trim()) return

      this.saving = true
      const view = await this.save(this.name.trim(), this.current, {
        isDefault: this.makeDefault,
        isShared:  this.shareIt,
      })
      this.saving = false

      if (!view) return   // 422 already surfaced; keep the dialog open so the name can be changed

      this.dialog = false
      this.activeId = view.id
    },
    async setDefault(view: SavedView) {
      await this.update(view, {is_default: !view.isDefault})
    },
    async toggleShared(view: SavedView) {
      await this.update(view, {is_shared: !view.isShared})
    },
    async destroy(view: SavedView) {
      if (!await this.$confirm("Delete view?", `"${view.name}" will be removed.`)) return
      if (await this.remove(view) && this.activeId === view.id) this.clear()
    },
  },
})
</script>

<template>
  <div class="app-saved-views d-flex align-center ga-2">
    <v-menu
      :close-on-content-click="false"
      location="bottom start"
    >
      <template #activator="{props: menuProps}">
        <v-btn
          v-bind="menuProps"
          append-icon="expand_more"
          prepend-icon="view_list"
          variant="tonal"
        >
          {{ activeName }}
        </v-btn>
      </template>

      <v-card min-width="320">
        <v-list density="compact">
          <v-list-item
            prepend-icon="table_chart"
            title="All records"
            @click="clear"
          />

          <template v-if="mine.length">
            <v-divider />
            <v-list-subheader>My views</v-list-subheader>
            <v-list-item
              v-for="view in mine"
              :key="view.id"
              :active="activeId === view.id"
              :title="view.name"
              @click="apply(view)"
            >
              <template #append>
                <v-btn
                  :color="view.isDefault ? 'warning' : undefined"
                  :icon="view.isDefault ? 'star' : 'star_border'"
                  :aria-label="view.isDefault ? 'Stop using this as the default view' : 'Make this the default view'"
                  size="x-small"
                  variant="text"
                  @click.stop="setDefault(view)"
                />
                <v-btn
                  v-if="allowSharing"
                  :color="view.isShared ? 'primary' : undefined"
                  :icon="view.isShared ? 'group' : 'people_outline'"
                  :aria-label="view.isShared ? 'Stop sharing this view' : 'Share this view'"
                  size="x-small"
                  variant="text"
                  @click.stop="toggleShared(view)"
                />
                <v-btn
                  color="error"
                  icon="delete_outline"
                  aria-label="Delete this saved view"
                  size="x-small"
                  variant="text"
                  @click.stop="destroy(view)"
                />
              </template>
            </v-list-item>
          </template>

          <template v-if="shared.length">
            <v-divider />
            <v-list-subheader>Shared with the team</v-list-subheader>
            <!-- No edit controls: a shared view someone else owns is
                 read-only, and offering the buttons only to 403 is worse than
                 not offering them. -->
            <v-list-item
              v-for="view in shared"
              :key="view.id"
              :active="activeId === view.id"
              :subtitle="view.ownerName || undefined"
              :title="view.name"
              @click="apply(view)"
            />
          </template>
        </v-list>

        <v-divider />

        <v-card-actions>
          <v-btn
            block
            prepend-icon="save"
            variant="text"
            @click="openSave"
          >
            Save current filters
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-menu>

    <v-dialog
      v-model="dialog"
      max-width="440"
    >
      <v-card title="Save this view">
        <v-card-text>
          <AppTextField
            v-model="name"
            autofocus
            label="Name"
            name="name"
            @keyup.enter="submit"
          />
          <v-switch
            v-model="makeDefault"
            color="primary"
            density="compact"
            hide-details
            label="Open this screen on it"
          />
          <v-switch
            v-if="allowSharing"
            v-model="shareIt"
            color="primary"
            density="compact"
            hide-details
            label="Share with the team"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn
            variant="text"
            @click="dialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            :disabled="!name.trim()"
            :loading="saving"
            variant="flat"
            @click="submit"
          >
            Save
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

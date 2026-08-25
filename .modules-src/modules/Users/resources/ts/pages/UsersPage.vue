<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h4">
        Users
      </h1>
      <v-spacer />
      <v-btn
        color="primary"
        prepend-icon="person_add"
        @click="add"
      >
        New User
      </v-btn>
    </div>

    <v-card class="mb-4">
      <v-card-text class="d-flex ga-3 flex-wrap align-center">
        <v-text-field
          v-model="filters.search"
          clearable
          density="comfortable"
          hide-details
          label="Search name or email"
          max-width="320"
          prepend-inner-icon="search"
        />
        <v-select
          v-model="filters.status"
          density="comfortable"
          hide-details
          :items="statuses"
          label="Status"
          max-width="200"
        />
      </v-card-text>
    </v-card>

    <app-pagination-table
      ref="table"
      endpoint="manage/users"
      :filters="filters"
      :headers="headers"
      :items-per-page="25"
      striped-rows
    >
      <template #[`item.name`]="{ item }">
        <div class="d-flex align-center ga-3">
          <v-avatar
            :color="item.isActive ? 'primary' : 'surface-variant'"
            size="32"
            variant="tonal"
          >
            <v-icon
              :icon="item.isActive ? 'person' : 'person_off'"
              size="18"
            />
          </v-avatar>
          <div>
            <div>{{ item.name }}</div>
            <div class="text-caption text-medium-emphasis">
              {{ item.email }}
            </div>
          </div>
        </div>
      </template>

      <template #[`item.status`]="{ item }">
        <v-chip
          :color="item.isActive ? 'success' : 'warning'"
          size="small"
        >
          {{ item.isActive ? 'Active' : 'Deactivated' }}
        </v-chip>
        <v-chip
          v-if="!item.emailVerified"
          class="ml-1"
          size="small"
          variant="tonal"
        >
          Unverified
        </v-chip>
      </template>

      <template #[`item.lastLoginAt`]="{ item }">
        <app-time-ago
          v-if="item.lastLoginAt"
          :value="item.lastLoginAt"
        />
        <span
          v-else
          class="text-medium-emphasis"
        >Never</span>
      </template>

      <template #[`item.actions`]="{ item }">
        <!-- flex-nowrap: three small buttons overflow the actions column at its
             natural width and the third wraps onto a second line, which grows
             the row and reads as a layout bug. -->
        <div class="d-flex justify-end flex-nowrap ga-1">
          <v-btn
            icon="edit"
            size="small"
            title="Edit"
            variant="text"
            @click.stop="edit(item)"
          />
          <!--
            Hidden on the viewer's own row rather than offered and then refused
            with a 422. The server enforces both guards regardless.
          -->
          <v-btn
            v-if="!item.isSelf"
            :icon="item.isActive ? 'person_off' : 'how_to_reg'"
            size="small"
            :title="item.isActive ? 'Deactivate' : 'Reactivate'"
            variant="text"
            @click.stop="toggleActive(item)"
          />
          <v-btn
            v-if="!item.isSelf"
            color="error"
            icon="delete"
            size="small"
            title="Delete"
            variant="text"
            @click.stop="destroy(item)"
          />
        </div>
      </template>

      <template #no-data>
        <app-empty-state
          description="Nobody matches those filters."
          icon="group"
          title="No users"
        />
      </template>
    </app-pagination-table>

    <user-form-dialog
      v-model="dialog"
      :user="editing"
      @saved="onSaved"
    />
  </v-container>
</template>

<script lang="ts">
import {defineComponent} from "vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import AppPaginationTable from "@/components/AppPaginationTable.vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"
import UserFormDialog from "@modules/Users/resources/ts/components/UserFormDialog.vue"
import useUsers, {type ManagedUser} from "@modules/Users/resources/ts/composables/useUsers"

export default defineComponent({
  name: "UsersPage",
  components: {AppEmptyState, AppPaginationTable, AppTimeAgo, UserFormDialog},
  setup() {
    return useUsers()
  },
  data() {
    return {
      // `status: null` rather than omitted: AppPaginationTable diffs the filter
      // object by JSON, and a key appearing for the first time on selection
      // reads as a change either way — but a stable shape keeps the diff honest.
      filters: {search: '', status: null as string | null},
      dialog: false,
      editing: null as ManagedUser | null,
      statuses: [
        {title: 'All', value: null},
        {title: 'Active', value: 'active'},
        {title: 'Deactivated', value: 'deactivated'},
      ],
      headers: [
        {title: 'User', key: 'name', sortable: false},
        {title: 'Status', key: 'status', sortable: false},
        {title: 'Last seen', key: 'lastLoginAt', sortable: false},
        {title: '', key: 'actions', sortable: false, align: 'end' as const, width: 170, nowrap: true},
      ],
    }
  },
  methods: {
    reload() {
      (this.$refs.table as InstanceType<typeof AppPaginationTable>)?.reload()
    },
    add() {
      this.editing = null
      this.dialog = true
    },
    edit(user: ManagedUser) {
      this.editing = user
      this.dialog = true
    },
    onSaved() {
      this.dialog = false
      this.reload()
    },
    async toggleActive(user: ManagedUser) {
      if (user.isActive && !await this.$confirm(
        'Deactivate user?',
        `${user.name} will be signed out everywhere and will not be able to sign back in.`
      )) return

      if (await this.setActive(user, !user.isActive)) this.reload()
    },
    async destroy(user: ManagedUser) {
      if (!await this.$confirm(
        'Delete user?',
        `${user.name} will be removed permanently. Deactivating instead keeps their history.`,
        'error'
      )) return

      if (await this.remove(user)) this.reload()
    },
  },
})
</script>

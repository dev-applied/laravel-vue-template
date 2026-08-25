<script lang="ts">
import {defineComponent} from "vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import AppTextField from "@/components/fields/AppTextField.vue"

interface Permission { id: number, name: string, group: string }
interface Role { id: number, name: string, permissions: Permission[], usersCount?: number }

/** Role CRUD with a permission matrix grouped by the dotted prefix. */
export default defineComponent({
  name: "RolesPage",
  components: {AppEmptyState, AppTextField},
  data() {
    return {
      roles: [] as Role[],
      permissions: [] as Permission[],
      loading: false,
      dialog: false,
      saving: false,
      editing: null as Role | null,
      form: {name: "", permissions: [] as string[]},
      errors: {} as Record<string, string[]>,
    }
  },
  computed: {
    grouped(): Record<string, Permission[]> {
      return this.permissions.reduce((acc: Record<string, Permission[]>, permission) => {
        (acc[permission.group] ??= []).push(permission)

        return acc
      }, {})
    },
    title(): string {
      return this.editing ? `Edit ${this.editing.name}` : "New role"
    },
  },
  async created() {
    await Promise.all([this.load(), this.loadPermissions()])
  },
  methods: {
    async load() {
      this.loading = true
      const response = await this.$http.get("/roles").catch(e => e)
      this.loading = false
      if (this.$error(response.status, response.data?.message)) return

      this.roles = response.data.data
    },
    async loadPermissions() {
      const response = await this.$http.get("/permissions").catch(e => e)
      if (this.$error(response.status, response.data?.message)) return

      this.permissions = response.data.permissions
    },
    open(role: Role | null) {
      this.editing = role
      this.errors = {}
      this.form = {
        name: role?.name ?? "",
        permissions: role?.permissions.map(p => p.name) ?? [],
      }
      this.dialog = true
    },
    togglePermission(name: string) {
      const at = this.form.permissions.indexOf(name)

      if (at === -1) {
        this.form.permissions.push(name)
      } else {
        this.form.permissions.splice(at, 1)
      }
    },
    toggleGroup(group: string) {
      const names = this.grouped[group].map(p => p.name)
      const allOn = names.every(n => this.form.permissions.includes(n))

      this.form.permissions = allOn
        ? this.form.permissions.filter(n => !names.includes(n))
        : [...new Set([...this.form.permissions, ...names])]
    },
    async save() {
      this.saving = true
      this.errors = {}

      const request = this.editing
        ? this.$http.put(`/roles/${this.editing.id}`, this.form)
        : this.$http.post("/roles", this.form)

      const response = await request.catch(e => e)
      this.saving = false

      if (response.status === 422) {
        this.errors = response.data.errors ?? {}

        return
      }
      if (this.$error(response.status, response.data?.message)) return

      this.dialog = false
      await this.load()
    },
    async remove(role: Role) {
      if (!await this.$confirm(`Delete the ${role.name} role?`, "Delete role")) return

      const response = await this.$http.delete(`/roles/${role.id}`).catch(e => e)
      // 409 means the role is still assigned; the API message says so.
      if (this.$error(response.status, response.data?.message)) return

      await this.load()
    },
  },
})
</script>

<template>
  <v-container>
    <div class="d-flex align-center flex-wrap mb-4 ga-2">
      <h1 class="text-h4">
        Roles
      </h1>
      <v-spacer />
      <v-btn
        color="primary"
        prepend-icon="add"
        @click="open(null)"
      >
        New role
      </v-btn>
    </div>

    <v-card :loading="loading">
      <v-table v-if="roles.length">
        <thead>
          <tr>
            <th>Role</th>
            <th>Permissions</th>
            <th>Users</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="role in roles"
            :key="role.id"
          >
            <td class="font-weight-medium">
              {{ role.name }}
            </td>
            <td class="text-caption">
              {{ role.permissions.length }} granted
            </td>
            <td>{{ role.usersCount ?? 0 }}</td>
            <td class="text-right">
              <v-btn
                icon="edit"
                size="small"
                variant="text"
                aria-label="Edit role"
                @click="open(role)"
              />
              <v-btn
                icon="delete"
                size="small"
                variant="text"
                aria-label="Delete role"
                @click="remove(role)"
              />
            </td>
          </tr>
        </tbody>
      </v-table>

      <AppEmptyState
        v-else-if="!loading"
        icon="badge"
        title="No roles yet"
        description="Create one to start granting permissions."
      />
    </v-card>

    <v-dialog
      v-model="dialog"
      max-width="720"
    >
      <v-card>
        <v-card-title>{{ title }}</v-card-title>
        <v-divider />
        <v-card-text>
          <AppTextField
            v-model="form.name"
            label="Role name"
            :error-messages="errors.name"
          />

          <div
            v-for="(items, group) in grouped"
            :key="group"
            class="mb-3"
          >
            <div class="d-flex align-center">
              <strong class="text-subtitle-2 text-capitalize">{{ group }}</strong>
              <v-btn
                size="x-small"
                variant="text"
                @click="toggleGroup(group)"
              >
                Toggle all
              </v-btn>
            </div>
            <!-- Plain chips, deliberately NOT a v-chip-group. One group was
                 rendered per permission section, every one bound to the same
                 `form.permissions` array — and Vuetify's group reads and writes
                 that model through its OWN registered items only, so ticking a
                 permission in a second section silently cleared the first. The
                 saved role kept whichever section was touched last. -->
            <div class="d-flex flex-wrap ga-2">
              <!-- No `model-value` binding. On a STANDALONE v-chip that prop is
                   existence, not selection — Vuetify renders the chip only when
                   it is true — so binding it to "is this permission selected"
                   made every unselected chip vanish entirely. Inside a
                   v-chip-group the group owns selection instead, which is what
                   made the difference invisible until the page was opened. -->
              <v-chip
                v-for="permission in items"
                :key="permission.id"
                :color="form.permissions.includes(permission.name) ? 'primary' : undefined"
                :variant="form.permissions.includes(permission.name) ? 'flat' : 'outlined'"
                :prepend-icon="form.permissions.includes(permission.name) ? 'check' : undefined"
                size="small"
                @click="togglePermission(permission.name)"
              >
                {{ permission.name }}
              </v-chip>
            </div>
          </div>

          <AppEmptyState
            v-if="!permissions.length"
            icon="lock"
            title="No permissions defined"
            description="Permissions come from your application's seeder, not this screen."
          />
        </v-card-text>
        <v-divider />
        <v-card-actions>
          <v-spacer />
          <v-btn @click="dialog = false">
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            :loading="saving"
            @click="save"
          >
            Save
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

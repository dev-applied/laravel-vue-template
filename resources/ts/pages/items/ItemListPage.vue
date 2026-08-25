<template>
  <v-container>
    <div class="d-flex align-center flex-wrap ga-2 mb-4">
      <h1 class="text-headline-large">
        Items
      </h1>
      <v-spacer />
      <!-- Only when the module is installed. `component :is` with a null
           resolves to nothing, so a project that removes Exports loses the
           button rather than getting a render error. -->
      <component
        :is="exportButton"
        v-if="exportButton"
        :filters="filters"
        source="items"
      />
      <v-btn
        color="primary"
        prepend-icon="add"
        @click="goCreate"
      >
        New Item
      </v-btn>
    </div>

    <ItemFilterBar
      v-model="filters"
      class="mb-4"
    />

    <app-pagination-table
      ref="table"
      endpoint="items"
      :headers="headers"
      :filters="filters"
      :items-per-page="25"
      striped-rows
      @click:row="onRowClick"
    >
      <template #[`item.status`]="{ item }">
        <v-chip
          :color="statusColor(item.status)"
          size="small"
        >
          {{ item.status }}
        </v-chip>
      </template>

      <template #[`item.due_date`]="{ item }">
        {{ formatDate(item.due_date) }}
      </template>

      <template #[`item.owner`]="{ item }">
        {{ item.owner?.full_name ?? '—' }}
      </template>

      <template #[`item.actions`]="{ item }">
        <v-btn
          icon="edit"
          aria-label="Edit item"
          variant="text"
          size="small"
          @click.stop="goEdit(item.id)"
        />
        <v-btn
          icon="delete"
          aria-label="Delete item"
          variant="text"
          size="small"
          color="error"
          @click.stop="confirmDelete(item)"
        />
      </template>
    </app-pagination-table>
  </v-container>
</template>

<script lang="ts">
import { defineAsyncComponent, defineComponent } from "vue"
import ItemFilterBar from "./_components/ItemFilterBar.vue"
import AppPaginationTable from "@/components/AppPaginationTable.vue"
import dayjs from "@/utils/dayjs"

// import.meta.glob, not a static import: modules/Exports may not be installed,
// and a static path to a missing file fails the whole Vite build rather than
// just this page. Same idiom LoginPage uses for SsoButtons.
const exportGlob = import.meta.glob("/modules/Exports/resources/ts/components/AppExportButton.vue")
const exportPath = "/modules/Exports/resources/ts/components/AppExportButton.vue"

export default defineComponent({
  components: { ItemFilterBar, AppPaginationTable },
  data() {
    return {
      exportButton: exportGlob[exportPath] ? defineAsyncComponent(exportGlob[exportPath] as never) : null,
      filters: {
        search:   "",
        status:   null as string | null,
        owner_id: null as number | null,
      },
      headers: [
        { title: "Name",     key: "name",     sortable: true },
        { title: "Status",   key: "status",   sortable: false },
        { title: "Priority", key: "priority", sortable: true },
        { title: "Due",      key: "due_date", sortable: true },
        { title: "Owner",    key: "owner",    sortable: false },
        { title: "",         key: "actions",  sortable: false, align: "end" as const, width: 110 },
      ],
    }
  },
  methods: {
    statusColor(status: string): string {
      return ({ active: "success", archived: "grey", draft: "warning" } as Record<string, string>)[status] ?? "grey"
    },
    formatDate(date: string | null): string {
      return date ? dayjs(date).format("MMM D, YYYY") : "—"
    },
    goCreate() {
      this.$router.push(this.$routeTo(this.ROUTES.ITEMS_CREATE))
    },
    goEdit(id: number) {
      // Route params are strings; passing a number relied on the router
      // stringifying it, which the param type does not promise.
      this.$router.push(this.$routeTo(this.ROUTES.ITEMS_EDIT, {id: String(id)}))
    },
    onRowClick(_event: PointerEvent, { item }: { item: { id: number } }) {
      this.goEdit(item.id)
    },
    async confirmDelete(item: { id: number; name: string }) {
      const ok = await this.$confirm(
        "Delete item?",
        `"${item.name}" will be archived. This can be undone.`,
        "error",
        { buttonTrueText: "Delete", buttonFalseText: "Cancel", buttonTrueColor: "error" },
      )
      if (!ok) return
      await this.$http.delete(`/items/${item.id}`).catch(e => e)
      // AppPaginationTable.reload() is exposed via defineExpose; using a ref
      // is more reliable than mutating filters, which short-circuits when the
      // values are identical (deep-equal watcher in AppPaginationTable).
      const table = this.$refs.table as { reload?: () => Promise<void> } | undefined
      await table?.reload?.()
    },
  },
})
</script>

<style lang="scss" scoped></style>

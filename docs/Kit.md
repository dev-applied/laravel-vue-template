# Cross-Project Component Kit

Catalog of the small-but-everywhere components and composables shipped in the template. None are required; pick what fits the project.

> **AI agents:** before building a new component that feels "common," check this list first. The 30-second copy-paste from here beats a new bespoke component every time.

## Components (`resources/ts/components/`)

| Component | One-liner | Use for |
| --- | --- | --- |
| `AppPageHeader` | Title + subtitle + breadcrumbs + actions slot. | The top of every page. |
| `AppStatusBadge` | v-chip with built-in color/icon map for the dozen statuses common across apps. | Lists, detail pages, anywhere status is shown. |
| `AppTimeAgo` | "2 minutes ago" with hover-tooltip for exact timestamp; auto-refreshes. | Anywhere a timestamp is shown. |
| `AppMoneyDisplay` | Locale-aware currency formatting, tabular numerals, negative styling. | Anywhere money is shown. |
| `AppCopyableField` | Inline value + copy-to-clipboard with morph-to-check feedback. | IDs, tokens, share URLs, hashes. |
| `AppInfoTooltip` | `?` icon with hover/focus/click tooltip, keyboard-accessible. | Inline help on form labels and table headers. |
| `AppFilterChips` | Active filters rendered as removable chips with "Clear all". | Top of any list page using filters. |
| `AppConfirmActionButton` | Button that opens `$confirm` then runs the action with loading state. | Every destructive action. |
| `AppImpersonationBanner` | Sticky "Stop impersonating" banner; pairs with existing backend impersonation endpoints. | Admin areas where staff acts as users. |
| `AppNotificationsBell` | Bell + unread badge + dropdown of recent notifications. Ships with `modules/Notifications` (`module:add Notifications`), which also provides the wired `NotificationsBell` container. | App-bar slot. |
| `AppWizardSteps` | Multi-step form scaffold with valid-per-step gating. | Onboarding, CSV import, checkout, multi-section forms. |
| `AppInlineEditField` | Click value to edit; Enter/blur saves, Escape cancels; async save support. | Editable cells / quick admin tweaks. |
| `AppDateRangePicker` | Start + end date inputs + preset chips (Today / Last 30 / YTD / etc.). | Reports, log views, transaction filters. |

## Layouts (`resources/ts/layouts/`)

| Layout | One-liner |
| --- | --- |
| `AdminLayout` | App bar + responsive nav drawer + user menu + slots for everything. |

## Composables (`resources/ts/composables/`)

| Composable | Signature | Use for |
| --- | --- | --- |
| `useApi<T>(endpoint, opts?)` | `{ data, error, loading, refresh }` | Fetch-on-mount pages with reactive state. |
| `useFilters(initial, opts?)` | `{ filters, reset, isActive }` | URL-synced filter state on any list page. |
| `useSelection<K>()` | `{ selectedKeys, count, isSelected, toggle, toggleAll, ... }` | Multi-select tables with bulk actions. |
| `useDocumentTitle(title, opts?)` | (void) | Set document.title for the page's lifetime, with auto-restore. |

## Patterns demonstrated together

A typical list page in any project on this stack:

```vue
<template>
  <AppPageHeader title="Items" :breadcrumbs="crumbs">
    <template #actions>
      <v-btn color="primary" @click="goCreate">New</v-btn>
    </template>
  </AppPageHeader>

  <ItemFilterBar v-model="filters" />
  <AppFilterChips v-model="filters" :labels="{ owner_id: 'Owner' }" />

  <AppPaginationTable endpoint="items" :filters="filters">
    <template #[`item.status`]="{ item }">
      <AppStatusBadge :value="item.status" />
    </template>
    <template #[`item.created_at`]="{ item }">
      <AppTimeAgo :value="item.created_at" />
    </template>
    <template #[`item.amount`]="{ item }">
      <AppMoneyDisplay :value="item.amount" />
    </template>
    <template #[`item.actions`]="{ item }">
      <AppConfirmActionButton
        text="Delete"
        :confirm-message="`Delete &quot;${item.name}&quot;?`"
        @confirmed="destroy(item)"
      />
    </template>
  </AppPaginationTable>
</template>

<script lang="ts">
import { defineComponent } from "vue"
import AppPageHeader from "@/components/AppPageHeader.vue"
import AppStatusBadge from "@/components/AppStatusBadge.vue"
import AppTimeAgo from "@/components/AppTimeAgo.vue"
import AppMoneyDisplay from "@/components/AppMoneyDisplay.vue"
import AppFilterChips from "@/components/AppFilterChips.vue"
import AppConfirmActionButton from "@/components/AppConfirmActionButton.vue"
import { useFilters } from "@/composables/useFilters"
import { useDocumentTitle } from "@/composables/useDocumentTitle"

export default defineComponent({
  components: {
    AppPageHeader, AppStatusBadge, AppTimeAgo, AppMoneyDisplay,
    AppFilterChips, AppConfirmActionButton,
  },
  setup() {
    useDocumentTitle("Items")
    const { filters } = useFilters({ search: "", status: null, owner_id: null })
    return { filters }
  },
  // ...
})
</script>
```

The 5 components + 2 composables in that snippet remove ~150 lines of boilerplate that would otherwise live in the page itself.

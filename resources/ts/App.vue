<template>
  <component
    :is="layout"
    v-if="layout"
  >
    <router-view />
  </component>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
export default defineComponent({
  head() {
    return {
      titleTemplate: "%s | " + import.meta.env.VITE_APP_NAME,
      title: this.$route?.meta?.title || this.humanise(this.$route?.name) || undefined
    }
  },
  methods: {
    /**
     * Last-resort title for a route that never called .title().
     *
     * The old fallback printed the route NAME verbatim, so tabs read
     * "booking.show" and "tasks.index". Route names are dotted identifiers, so
     * the last segment is the action and the one before it is the subject —
     * "support.tickets" reads better as "Tickets" than as either half of the
     * raw name. Not a substitute for .title(); just a floor.
     */
    humanise(name: unknown): string | undefined {
      if (typeof name !== "string" || !name) return undefined

      const parts = name.split(".")
      const word  = parts.length > 1 && ["index", "show", "list"].includes(parts[parts.length - 1])
        ? parts[parts.length - 2]
        : parts[parts.length - 1]

      return word
        .replace(/[-_]+/g, " ")
        // Lower-cased on purpose: the hyphen path above already yields
        // sentence case ("audit-log" -> "Audit log"), and the explicit .title()
        // calls are written that way too. Left as "$1 $2" this produced
        // "Invoice History" beside "Audit log".
        .replace(/([a-z])([A-Z])/g, (_m, a: string, b: string) => `${a} ${b.toLowerCase()}`)
        .replace(/^./, c => c.toUpperCase())
    },
  },
  computed: {
    layout(): string | null{
      if (!this.$route) return null
      return this.$route.meta?.layout ? this.$route.meta.layout + "Layout" : "EmptyLayout"
    }
  }
})
</script>

<template>
  <v-app>
    <app-messages />

    <v-app-bar
      :elevation="1"
      color="surface"
      density="comfortable"
    >
      <v-app-bar-nav-icon
        v-if="mdAndDown"
        @click="drawer = !drawer"
      />

      <slot name="brand">
        <v-app-bar-title class="font-weight-bold">
          {{ brand }}
        </v-app-bar-title>
      </slot>

      <v-spacer />

      <slot name="app-bar-actions" />

      <slot name="user-menu">
        <v-menu>
          <template #activator="{ props: act }">
            <v-btn
              v-bind="act"
              variant="text"
              append-icon="expand_more"
            >
              {{ userName }}
            </v-btn>
          </template>
          <v-list density="compact">
            <v-list-item
              prepend-icon="account_circle"
              title="Profile"
              @click="$emit('profile')"
            />
            <v-divider />
            <v-list-item
              prepend-icon="logout"
              title="Log out"
              @click="$emit('logout')"
            />
          </v-list>
        </v-menu>
      </slot>
    </v-app-bar>

    <v-navigation-drawer
      v-model="drawer"
      :rail="rail && !mdAndDown"
      :permanent="!mdAndDown"
      :temporary="mdAndDown"
      @click.self="rail = false"
    >
      <slot name="nav">
        <v-list
          v-if="navItems.length"
          density="compact"
          nav
        >
          <v-list-item
            v-for="item in navItems"
            :key="item.title"
            :prepend-icon="item.icon"
            :title="item.title"
            :to="item.to"
            :value="item.title"
          />
        </v-list>
        <!--
          Says why it is empty instead of rendering a blank column. `navItems`
          defaults to [], so a project that has not passed it yet — every
          project on day one — gets a drawer with nothing in it and no clue
          that a prop is missing. The Dashboard on the same screen already
          explains its own empty state; this matches it.
        -->
        <p
          v-else-if="!rail"
          class="text-body-small text-medium-emphasis pa-4"
        >
          No navigation items. Pass a <code>nav-items</code> prop, or override
          the <code>#nav</code> slot.
        </p>
      </slot>

      <template
        v-if="!mdAndDown"
        #append
      >
        <v-divider />
        <v-list>
          <v-list-item
            :prepend-icon="rail ? 'chevron_right' : 'chevron_left'"
            :title="rail ? '' : 'Collapse'"
            @click.stop="rail = !rail"
          />
        </v-list>
      </template>
    </v-navigation-drawer>

    <v-main>
      <v-container :fluid="fluid">
        <v-slide-x-reverse-transition mode="out-in">
          <span class="transition-wrapper">
            <app-error-boundary name="AdminLayout">
              <slot />
            </app-error-boundary>
          </span>
        </v-slide-x-reverse-transition>
      </v-container>
    </v-main>

    <update-detector />
  </v-app>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue"
import { useDisplay } from "vuetify"
import UpdateDetector from "@/components/UpdateDetector.vue"
import AppErrorBoundary from "@/components/AppErrorBoundary.vue"
import AppMessages from "@/components/AppMessages.vue"
import type { RouteLocationRaw } from "vue-router"

export interface NavItem {
  title: string
  icon?: string
  to:    RouteLocationRaw
}

export default defineComponent({
  components: {AppErrorBoundary,  AppMessages, UpdateDetector },
  props: {
    brand: {
      type:    String,
      default: "Admin",
    },
    userName: {
      type:    String,
      default: "Account",
    },
    navItems: {
      type:    Array as PropType<NavItem[]>,
      default: () => [],
    },
    fluid: {
      type:    Boolean,
      default: true,
    },
  },
  emits: ["profile", "logout"],
  setup() {
    const { mdAndDown } = useDisplay()
    return { mdAndDown }
  },
  data() {
    return {
      drawer: true,
      rail:   false,
    }
  },
})
</script>

<style lang="scss" scoped>
.transition-wrapper {
  display: block;
}
</style>

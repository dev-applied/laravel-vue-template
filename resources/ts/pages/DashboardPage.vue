<template>
  <v-container>
    <v-card>
      <v-card-title>Dashboard</v-card-title>
      <v-card-text>
        <p>
          Welcome to the dashboard!
        </p>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn
          color="primary"
          @click="logout"
        >
          Logout
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>

<script lang="ts">
import { defineComponent } from "vue"

export default defineComponent({
  methods: {
    async logout() {
      await this.$auth.logout()
      await this.$router.push(this.route(this.ROUTES.LOGIN))
    }
  }
})
</script>

<style lang="scss" scoped></style>

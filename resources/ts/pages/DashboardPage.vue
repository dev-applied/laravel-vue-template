<template>
  <v-container>
    <v-card>
      <v-card-title>Dashboard</v-card-title>
      <v-card-text>
        <p>
          Welcome to the dashboard!
        </p>
        <div>
          <app-auto-complete
            v-model="selectedUser"
            endpoint="users"
            label="Select User"
            :item-title="(item: any) => item.full_name"
          />
        </div>
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
import {defineComponent} from "vue"
import AppAutoComplete from "@/components/fields/AppAutoComplete/AppAutoComplete.vue"


export default defineComponent({
  components: {AppAutoComplete},
  data() {
    return {
      selectedUser: null
    }
  },
  methods: {
    async logout() {
      await this.$auth.logout()
      await this.$router.push(this.$routeTo(this.ROUTES.LOGIN))
    }
  }
})
</script>

<style lang="scss" scoped></style>

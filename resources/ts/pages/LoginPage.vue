<template>
  <v-container class="fill-height">
    <v-row>
      <v-col offset-md="3" md="6">
        <v-card>
          <v-card-title>Login</v-card-title>
          <v-card-text>
            <v-alert v-model="notAuthorized" color="error" dismissible>
              Your credentials are invalid. Please try again.
            </v-alert>
            <v-form ref="form">
              <v-text-field
                v-model="email"
                outlined
                prepend-inner-icon="mdi-account"
                :rules="rules.email"
                placeholder="Email"
                @keydown.enter="login"
              />
              <v-text-field
                v-model="password"
                outlined
                placeholder="Password"
                :rules="rules.required"
                prepend-inner-icon="mdi-lock"
                type="password"
                @keydown.enter="login"
              />
            </v-form>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn color="primary" :loading="loading" @click="login"> Login </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script lang="ts">
import validators from '@/mixins/validators'
import { defineComponent } from 'vue'

export default defineComponent({
  mixins: [validators],
  data() {
    return {
      loading: false,
      email: '',
      password: '',
      remember: false,
      notAuthorized: false
    }
  },
  methods: {
    async login() {
      if (!this.$refs!.form!.validate()) return
      this.loading = true
      const {
        data: { message, errors },
        status
      } = await this.$auth
        .login({
          email: this.email,
          password: this.password
        })
        .catch((e) => e)
      this.loading = false

      if (this.$error(status, message, errors, false)) {
        this.notAuthorized = true
        return
      }
      await this.$router.push(this.$route.query.to || '/dashboard')
    }
  }
})
</script>

<style lang="scss" scoped></style>

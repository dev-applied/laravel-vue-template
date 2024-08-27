<template>
  <v-container class="fill-height justify-center align-center">
    <app-table
      endpoint="/users"
    />
  </v-container>
</template>

<script lang="ts">
import validators from "@/mixins/validators"
import { defineComponent } from "vue"
import AppTable from "@/components/AppTable.vue"

export default defineComponent({
  components: { AppTable },
  mixins: [validators],
  data() {
    return {
      loading: false,
      email: "",
      password: "",
      remember: false,
      notAuthorized: false,
      notAuthorizedMessage: "",
      showForgotPasswordError: false,
      forgotPasswordError: ['*Please enter an email to reset your password.'],
      showPassword: false,
      showLogin: true,
    }
  },
  mounted() {
    if (this.$auth.user) {
      this.$router.push(this.$routeTo(this.ROUTES.DASHBOARD))
    }
  },
  computed: {
    title() {
      return this.showLogin ? 'User Login' : 'Email Sent'
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
        this.notAuthorizedMessage = message
        return
      }
      await this.$router.push(this.$route.query?.to || "/dashboard")
    },
    async sendResetPassword() {
      this.showForgotPasswordError = false
      if (!this.$refs!.email!.validate()) {
        this.showForgotPasswordError = true
        return
      }

      this.loading = true
      const { data: { message, errors }, status } = await this.$http.post('/forgot-password', {
        email: this.email
      }).catch(e => e)
      this.loading = false
      if (this.$error(status, message, errors)) {
        this.showForgotPasswordError = true
        this.forgotPasswordError = message
        return
      }
      this.showLogin = false
    }
  }
})
</script>

<style lang="scss" scoped></style>

<template>
  <v-container class="fill-height">
    <v-row>
      <v-col
        offset-md="3"
        md="6"
      >
        <v-card>
          <v-card-title>
            <h3>
              {{ title }}
            </h3>
<!--            <v-spacer />-->
<!--            <v-img-->
<!--              contain-->
<!--              :style="{ 'max-width': $vuetify.breakpoint.mdAndUp ? '160px' : '115px' }"-->
<!--              src="/images/logo.png"-->
<!--            />-->
          </v-card-title>
          <v-card-text>
            <template v-if="showLogin">
              <v-alert
                v-model="notAuthorized"
                color="error"
                dismissible
              >
                {{ notAuthorizedMessage }}
              </v-alert>
              <v-form ref="form">
                <v-text-field
                  ref="email"
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
                  :type="showPassword ? 'text' : 'password'"
                  @keydown.enter="login"
                >
                  <template #append>
                    <v-btn
                      icon
                      small
                      style="margin-top: -2px;"
                      tabindex="-1"
                      @click="showPassword = !showPassword"
                    >
                      <v-icon
                        size="20"
                      >
                        {{ !showPassword ? 'mdi-eye' : 'mdi-eye-off' }}
                      </v-icon>
                    </v-btn>
                  </template>
                </v-text-field>

                <div>
                  <span class="primary--text" role="button" @click="sendResetPassword">Forgot password?</span>
                </div>

                <v-messages
                  v-if="showForgotPasswordError"
                  v-model="forgotPasswordError"
                  class="mt-4"
                  color="error"
                />
              </v-form>
            </template>
            <template v-else>
              <div>
                <p>We've sent a password recovery email to {{ email }}.</p>
                <p>Please check your inbox and follow the instructions to reset your password.</p>
              </div>
            </template>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <template v-if="showLogin">
              <v-btn
                color="primary"
                :loading="loading"
                @click="login"
              >
                Login
              </v-btn>
            </template>
            <template v-else>
              <v-btn
                color="primary"
                @click="showLogin = true"
              >
                Return to Sign In
              </v-btn>
            </template>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script lang="ts">
import validators from "@/mixins/validators"
import { defineComponent } from "vue"

export default defineComponent({
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
      this.$router.push(this.route(this.ROUTES.DASHBOARD))
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

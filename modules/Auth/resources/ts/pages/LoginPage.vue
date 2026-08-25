<template>
  <v-container class="fill-height">
    <v-row>
      <v-col
        md="6"
        offset-md="3"
      >
        <v-card>
          <v-card-title>
            <h1 class="text-display-small">
              {{ title }}
            </h1>
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
                closable
              >
                {{ notAuthorizedMessage }}
              </v-alert>
              <v-form ref="form">
                <v-text-field
                  ref="email"
                  v-model="email"
                  variant="outlined"
                  placeholder="Email"
                  aria-label="Email"
                  type="email"
                  autocomplete="username"
                  prepend-inner-icon="account_circle"
                  :rules="[rules.email(), rules.required()]"
                  @keydown.enter="login"
                />
                <v-text-field
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  variant="outlined"
                  placeholder="Password"
                  aria-label="Password"
                  autocomplete="current-password"
                  prepend-inner-icon="lock"
                  :rules="[rules.required()]"
                  @keydown.enter="login"
                >
                  <template #append>
                    <v-btn
                      icon
                      size="small"
                      style="margin-top: -2px;"
                      :aria-label="showPassword ? 'Hide the password' : 'Show the password'"
                      @click="showPassword = !showPassword"
                    >
                      <v-icon
                        size="20"
                      >
                        {{ !showPassword ? 'visibility' : 'visibility_off' }}
                      </v-icon>
                    </v-btn>
                  </template>
                </v-text-field>

                <div>
                  <span
                    class="text-primary"
                    role="button"
                    @click="sendResetPassword"
                  >Forgot password?</span>
                </div>

                <!-- :messages + active, NOT v-model. VMessages declares `active`, `color`
             and `messages` and has no modelValue at all, so the bound string fell
             through as a stray DOM attribute and every forgot-password error —
             seeded hint and server response alike — rendered as an empty div. -->
                <v-messages
                  v-if="showForgotPasswordError"
                  :messages="forgotPasswordError"
                  active
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

            <!--
              Only present in the sso=oidc variant; `none` drops the file, and
              the component resolves to null so nothing renders.
            -->
            <component
              :is="ssoButtons"
              v-if="showLogin && ssoButtons"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <template v-if="showLogin">
              <v-btn
                :loading="loading"
                color="primary"
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
import {defineAsyncComponent, defineComponent, markRaw} from "vue"

// import.meta.glob rather than a bare dynamic import: the `none` SSO variant
// DELETES SsoButtons.vue, and a static path to a missing module fails the whole
// build rather than just this component.
const ssoGlob = import.meta.glob('/modules/Auth/resources/ts/components/SsoButtons.vue')
const ssoPath = '/modules/Auth/resources/ts/components/SsoButtons.vue'

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
      // markRaw: a component object stored in data() gets made reactive, which
      // Vue warns about at runtime — it deep-proxies the whole component
      // definition for no benefit, since it never changes after load.
      ssoButtons: ssoGlob[ssoPath] ? markRaw(defineAsyncComponent(ssoGlob[ssoPath] as never)) : null,
    }
  },
  computed: {
    title() {
      return this.showLogin ? 'User Login' : 'Email Sent'
    }
  },
  mounted() {
    if (this.$auth.user) {
      this.$router.push(this.$routeTo(this.ROUTES.DASHBOARD))
    }
    this.email = localStorage.getItem('remember') || ''
    if (this.email) {
      this.remember = true
    }
  },
  methods: {
    async login() {
      // Destructure `valid`. VForm.validate() resolves to an OBJECT
      // ({valid, errors}), and an object is always truthy — so the previous
      // `if (!await ...validate()) return` could never fire and the form
      // submitted regardless of what the client-side rules said. The server
      // still validates, so nothing was insecure; the rules were simply
      // decorative.
      const {valid} = await (this.$refs.form as Vuetify.VForm).validate()

      if (!valid) return
      this.loading = true
      const {
        data: {message, errors},
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

      if (this.remember) {
        localStorage.setItem('remember', this.email)
      } else {
        localStorage.removeItem('remember')
      }

      // `to` is a PATH (Authorization.ts deep-links with one), so it is pushed
      // as-is. The fallback is a route NAME, and pushing a bare name string
      // makes vue-router treat it as a path — which sent every successful
      // login to /dashboard.index and a 404. mounted() five lines up always
      // had this right; only this branch did not.
      const deepLink = this.$route.query?.to

      await this.$router.push(
        deepLink ? String(deepLink) : this.$routeTo(this.ROUTES.DASHBOARD)
      )
    },
    async sendResetPassword() {
      this.showForgotPasswordError = false

      // A field's validate() resolves to an ARRAY of error messages, and an
      // empty array is truthy too — same shape of bug as login() above, so
      // this branch never ran either and an empty address was sent to the API.
      const fieldErrors = await (this.$refs.email as { validate: (silent?: boolean) => Promise<string[]> }).validate()

      if (fieldErrors.length) {
        this.showForgotPasswordError = true
        return
      }

      this.loading = true
      const {data: {message, errors}, status} = await this.$http.post('/forgot-password', {
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

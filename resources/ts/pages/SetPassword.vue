<template>
  <v-container class="fill-height set-password">
    <v-row>
      <v-col
        offset-md="3"
        md="6"
        sm="8"
        offset-sm="2"
      >
        <v-card>
          <v-card-title>
            <h3 class="set-password__title">
              Set a Password
            </h3>
<!--            <v-spacer />-->
<!--            <v-img-->
<!--              contain-->
<!--              :style="{ 'max-width': $vuetify.breakpoint.mdAndUp ? '160px' : '115px' }"-->
<!--              src="/images/logo.png"-->
<!--            />-->
          </v-card-title>
          <v-card-text class="mt-3">
            <v-alert
              v-if="errorMessage"
              v-model="errorMessage"
              color="error"
              class=""
              dismissible
            >
              {{ errorMessage }}
            </v-alert>
            <v-form
              ref="form"
              v-model="valid"
            >
              <v-text-field
                ref="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                outlined
                prepend-inner-icon="mdi-lock"
                :rules="rules.password"
                placeholder="Password*"
                autocomplete="new-password"
                @keyup="$refs.confirmPassword.validate(true)"
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
              <v-text-field
                ref="confirmPassword"
                v-model="confirm_password"
                :rules="confirmPasswordRules(password, confirm_password)"
                outlined
                placeholder="Confirm Password*"
                autocomplete="new-password"
                prepend-inner-icon="mdi-lock"
                :type="showConfirmPassword ? 'text' : 'password'"
              >
                <template #append>
                  <v-btn
                    icon
                    small
                    style="margin-top: -2px;"
                    tabindex="-1"
                    @click="showConfirmPassword = !showConfirmPassword"
                  >
                    <v-icon
                      size="20"
                    >
                      {{ !showConfirmPassword ? 'mdi-eye' : 'mdi-eye-off' }}
                    </v-icon>
                  </v-btn>
                </template>
              </v-text-field>

              <password-validation
                ref="validator"
                :password="password"
              />
            </v-form>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn
              color="secondary"
              outlined
              @click="$router.push($routeTo(ROUTES.LOGIN))"
            >
              Return to Sign In
            </v-btn>
            <v-btn
              :loading="loading"
              :disabled="!valid"
              class="set-password__action"
              color="primary"
              @click="setPassword"
            >
              Proceed
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script lang="ts">
import validators from "@/mixins/validators"
import PasswordValidation from '@/components/AppPasswordValidation.vue'
import {defineComponent} from "vue"
import {useUserStore} from "@/stores/user"
import type { VForm } from "vuetify/components"

export default defineComponent({
  components: {
    PasswordValidation
  },
  mixins: [validators],
  data() {
    return {
      loading: false,
      password: "",
      confirm_password: '',
      errorMessage: '' as string,
      showPassword: false,
      showConfirmPassword: false,
      valid: false
    }
  },
  computed: {
    token() {
      return this.$route.params.token
    },
    email(): string {
      return this.$route.query.email as string
    }
  },
  methods: {
    async setPassword() {
      if (!(await (this.$refs.form as VForm).validate())) return
      this.loading = true
      const {data: {message, errors}, status} = await this.$http.post('/forgot-password/reset', {
        email: this.email,
        token: this.token,
        password: this.password,
        password_confirmation: this.confirm_password
      }).catch(e => e)
      this.loading = false
      if (this.$error(status, message, errors)) {
        this.errorMessage = message
        return
      }

      await useUserStore().login({email: this.email, password: this.password})

      await this.$router.push(this.$routeTo(this.ROUTES.LOGIN))
    },
  }
})
</script>

<style lang="scss" scoped>
.set-password {
  &__forgot-password {
    text-align: left;
    margin-bottom: 0;

    span {
      color: var(--v-primary-base);
      text-decoration: none;
      transition: color 0.3s ease;
      cursor: pointer;

      &:hover {
        color: var(--v-primary-hover-base);
      }
    }
  }
}
</style>

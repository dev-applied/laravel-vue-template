<script lang="ts">
import {defineComponent} from "vue"
import AppTextField from "@/components/fields/AppTextField.vue"
import AppTextarea from "@/components/fields/AppTextarea.vue"
import AppSlotPicker from "@modules/Booking/resources/ts/components/AppSlotPicker.vue"
import useBooking, {type Slot} from "@modules/Booking/resources/ts/composables/useBooking"

export default defineComponent({
  name: "BookingPage",
  components: {AppTextField, AppTextarea, AppSlotPicker},
  props: {
    slug: {type: String, required: true},
    /** How many days of availability to load at once. Capped at 62 server-side. */
    days: {type: Number, default: 14},
  },
  setup(props) {
    return useBooking(props.slug)
  },
  data() {
    return {
      selected: null as Slot | null,
      details: {name: "", email: "", notes: ""},
    }
  },
  mounted() {
    this.load()
  },
  methods: {
    load() {
      const from = new Date()
      const to = new Date(from.getTime() + this.days * 86_400_000)
      this.fetchAvailability(from, to)
    },
    async submit() {
      if (!this.selected) return

      const ok = await this.book(this.selected, this.details)

      // A failed booking clears the selection: the slot it pointed at may no
      // longer exist, and the refreshed picker is the honest state.
      if (!ok) this.selected = null
    },
  },
})
</script>

<template>
  <v-container class="d-flex justify-center">
    <v-card
      class="pa-6"
      max-width="720"
      width="100%"
    >
      <template v-if="confirmed">
        <v-alert
          class="mb-4"
          :type="confirmed.status === 'pending' ? 'info' : 'success'"
          variant="tonal"
        >
          {{ confirmed.status === 'pending'
            ? 'Your request has been received and is awaiting confirmation.'
            : 'Your booking is confirmed.' }}
        </v-alert>

        <div class="text-body-2">
          <div><strong>Reference</strong> {{ confirmed.reference }}</div>
          <div><strong>When</strong> {{ dayLabel(confirmed.startsAt.slice(0, 10)) }}, {{ timeLabel(confirmed.startsAt) }}</div>
          <div v-if="confirmed.resource">
            <strong>Where</strong> {{ confirmed.resource.name }}
          </div>
        </div>

        <p class="text-caption text-medium-emphasis mt-4">
          Keep the reference — it is what lets you cancel.
        </p>
      </template>

      <template v-else>
        <h1 class="text-h5 mb-4">
          {{ resource?.name ?? 'Book a time' }}
        </h1>

        <AppSlotPicker
          :by-day="byDay"
          :day-label="dayLabel"
          :window-days="days"
          :loading="loading"
          :selected="selected"
          :time-label="timeLabel"
          :timezone="tz()"
          @select="selected = $event"
        />

        <!-- v-show, not v-if: mounting the form on selection would steal focus
             and reset anything already typed when the picker refreshes. -->
        <div v-show="selected">
          <v-divider class="my-4" />

          <!--
            A plain v-form, not AppServerValidationForm. That component OWNS the
            request (endpoint/method/data are required props) and useBooking's
            book() already does the POST, the $error surfacing and the 409
            refresh. Wrapping one in the other duplicated the request and hid
            this form's only submit button, because the component has a single
            default slot and no #actions.
          -->
          <v-form @submit.prevent="submit">
            <AppTextField
              v-model="details.name"
              label="Your name"
              name="name"
              required
            />
            <AppTextField
              v-model="details.email"
              label="Email"
              name="email"
              required
              type="email"
            />
            <AppTextarea
              v-model="details.notes"
              label="Anything we should know?"
              name="notes"
              rows="2"
            />

            <div class="d-flex justify-end mt-4">
              <v-btn
                color="primary"
                :loading="booking"
                type="submit"
                variant="flat"
              >
                Confirm booking
              </v-btn>
            </div>
          </v-form>
        </div>
      </template>
    </v-card>
  </v-container>
</template>

<script lang="ts">
import {defineComponent} from "vue"
import AppSettingField from "@modules/Settings/resources/ts/components/AppSettingField.vue"
import useSettings, {type SettingGroup} from "@modules/Settings/resources/ts/composables/useSettings"

export default defineComponent({
  name: "SettingsPage",
  components: {AppSettingField},
  setup() {
    return useSettings()
  },
  data() {
    return {
      // The same union AppSettingField's modelValue prop declares. `unknown`
      // is assignable to nothing, so every v-model onto a field was an error.
      form: {} as Record<string, string | number | boolean | object | null>,
      tab:  null as string | null,
    }
  },
  watch: {
    groups: {
      handler(groups: SettingGroup[]) {
        // Rebuilt from the server's echo after every save — that is what
        // re-masks a secret the user just replaced.
        const next: Record<string, string | number | boolean | object | null> = {}
        for (const group of groups) {
          for (const field of group.settings) next[field.key] = field.value
        }
        this.form = next
        this.tab ??= groups[0]?.group ?? null
      },
      immediate: true,
    },
  },
  mounted() {
    this.fetch()
  },
  methods: {
    submit() {
      this.save({...this.form})
    },
  },
})
</script>

<template>
  <v-container>
    <div class="d-flex align-center ga-2 mb-4">
      <h1 class="text-h4">
        Settings
      </h1>
      <v-spacer />
      <v-btn
        color="primary"
        :disabled="!loaded"
        :loading="saving"
        prepend-icon="save"
        variant="flat"
        @click="submit"
      >
        Save
      </v-btn>
    </div>

    <v-card>
      <v-progress-linear
        v-show="loading"
        indeterminate
      />

      <v-tabs
        v-model="tab"
        show-arrows
      >
        <v-tab
          v-for="group in groups"
          :key="group.group"
          :value="group.group"
        >
          {{ group.group }}
        </v-tab>
      </v-tabs>

      <v-divider />

      <v-tabs-window v-model="tab">
        <v-tabs-window-item
          v-for="group in groups"
          :key="group.group"
          :value="group.group"
        >
          <v-card-text>
            <v-row>
              <v-col
                v-for="field in group.settings"
                :key="field.key"
                cols="12"
                md="6"
              >
                <AppSettingField
                  v-model="form[field.key]"
                  :field="field"
                />
              </v-col>
            </v-row>
          </v-card-text>
        </v-tabs-window-item>
      </v-tabs-window>
    </v-card>
  </v-container>
</template>

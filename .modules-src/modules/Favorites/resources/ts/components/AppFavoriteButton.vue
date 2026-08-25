<template>
  <v-btn
    :aria-label="favorited ? `Remove ${label} from favourites` : `Add ${label} to favourites`"
    :aria-pressed="favorited"
    :color="favorited ? 'warning' : undefined"
    :density="density"
    :disabled="pending"
    icon
    :size="size"
    variant="text"
    @click.prevent.stop="onToggle"
  >
    <!-- star / star_border, the Material Icons ligatures. This project uses the
         `md` iconset, where an unknown name is drawn as literal text rather
         than erroring. -->
    <v-icon>{{ favorited ? 'star' : 'star_border' }}</v-icon>
  </v-btn>
</template>

<script lang="ts">
import {defineComponent, type PropType} from "vue"
import {useFavoriteToggle} from "@modules/Favorites/resources/ts/composables/useFavorites"

/**
 * A star, next to the thing it stars.
 *
 * `modelValue` is the initial state, usually from `isFavoritedBy` on the
 * serialised record. It is not a v-model in the two-way sense: the SERVER
 * decides the resulting state and this emits what came back, so a list that
 * tracks it stays honest even if two tabs disagree.
 */
export default defineComponent({
  name: "AppFavoriteButton",
  props: {
    /** The registry alias the project registered, e.g. "article". */
    type:       {type: String, required: true},
    recordId:   {type: [Number, String], required: true},
    modelValue: {type: Boolean, default: false},
    /** Used only in the aria-label, so screen readers say what is being starred. */
    label:   {type: String, default: "this"},
    size: {type: String, default: "small"},
    // Vuetify's own Density union, not a loose string — v-btn's prop will not
    // accept one.
    density: {
      type: String as PropType<"default" | "comfortable" | "compact">,
      default: "comfortable",
    },
  },
  emits: ['update:modelValue', 'change'],
  setup(props) {
    return useFavoriteToggle(props.type, props.recordId, props.modelValue)
  },
  watch: {
    // A parent re-fetching its list re-seeds the star.
    modelValue(value: boolean) {
      this.favorited = value
    },
  },
  methods: {
    async onToggle() {
      await this.toggle()

      this.$emit('update:modelValue', this.favorited)
      this.$emit('change', this.favorited)
    },
  },
})
</script>

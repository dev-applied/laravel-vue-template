<template>
  <v-container>
    <h1 class="text-headline-small mb-4">
      My favourites
    </h1>

    <v-skeleton-loader
      v-if="loading && !loaded"
      type="list-item-two-line@3"
    />

    <AppEmptyState
      v-else-if="!favorites.length"
      description="Star something and it will show up here."
      icon="star_border"
      title="Nothing starred yet"
    />

    <v-list v-else>
      <v-list-item
        v-for="favorite in favorites"
        :key="favorite.id"
        :subtitle="subtitleFor(favorite)"
      >
        <template #title>
          <!-- A favourite outlives a hard-deleted target, so the record can be
               null. Saying so is better than rendering a blank row. -->
          <span v-if="favorite.record">{{ favorite.record.label }}</span>
          <span
            v-else
            class="text-medium-emphasis font-italic"
          >No longer available</span>
        </template>

        <template #append>
          <AppFavoriteButton
            v-if="favorite.record && favorite.type"
            :label="favorite.record.label"
            model-value
            :record-id="favorite.record.id"
            :type="favorite.type"
            @change="(starred: boolean) => !starred && forget(favorite.id)"
          />
        </template>
      </v-list-item>
    </v-list>
  </v-container>
</template>

<script lang="ts">
import {defineComponent} from "vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import AppFavoriteButton from "@modules/Favorites/resources/ts/components/AppFavoriteButton.vue"
import useFavorites, {type FavoriteItem} from "@modules/Favorites/resources/ts/composables/useFavorites"

export default defineComponent({
  name: "FavoritesPage",
  components: {AppEmptyState, AppFavoriteButton},
  setup() {
    return useFavorites()
  },
  mounted() {
    this.fetch()
  },
  methods: {
    subtitleFor(favorite: FavoriteItem): string {
      const when = new Date(favorite.favoritedAt).toLocaleDateString()

      return favorite.type ? `${favorite.type} · starred ${when}` : `Starred ${when}`
    },
  },
})
</script>

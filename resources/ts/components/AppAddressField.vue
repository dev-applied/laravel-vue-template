<script setup lang="ts">
import {ref, watch} from 'vue'
import {usePlacesAutocomplete, geocodeByPlaceId, getLatLng} from 'vue-use-places-autocomplete'

defineOptions({
  inheritAttrs: false
})

type Suggestion = {
  description: string;
  distance_meters?: number;
  matched_substrings: {
    length: number;
    offset: number;
  }[];
  place_id: string;
  structured_formatting: {
    main_text: string;
    main_text_matched_substrings: {
      length: number;
      offset: number;
    }[];
    secondary_text: string;
  };
  terms: {
    offset: number;
    value: string;
  }[];
  types: string[];
}

const model = defineModel<null | undefined | string>({
  required: true,
})
const street = defineModel<null | undefined | string>('street')
const city = defineModel<null | undefined | string>('city')
const state = defineModel<null | undefined | string>('state')
const zipcode = defineModel<null | undefined | string>('zipcode')
const country = defineModel<null | undefined | string>('country')
const latitude = defineModel<null | undefined | number>('latitude')
const longitude = defineModel<null | undefined | number>('longitude')

const query = ref<string>('')
query.value = model.value || ''
watch(model, () => {
  query.value = model.value || ''
})
const { suggestions, loading } = usePlacesAutocomplete(query, {
  apiKey: import.meta.env.VITE_GOOGLE_API_KEY,
  debounce: 500,
  minLengthAutocomplete: 3
})

const findByType = (type: string, result: any) => {
  return result.address_components.find((component: any) => component.types.includes(type))
}

const setPlace = async (suggestion: Suggestion | null) => {
  if (!suggestion) {
    model.value = null
    street.value = null
    city.value = null
    state.value = null
    zipcode.value = null
    country.value = null
    latitude.value = null
    longitude.value = null
    return
  }
  const results = await geocodeByPlaceId(suggestion.place_id)
  const result = results[0]
  model.value = suggestion.description
  street.value = findByType('street_number', result)?.long_name + ' ' + findByType('route', result)?.long_name
  city.value = findByType('locality', result)?.long_name
  state.value = findByType('administrative_area_level_1', result)?.short_name
  zipcode.value = findByType('postal_code', result)?.long_name
  country.value = findByType('country', result)?.short_name
  const {lat, lng} = await getLatLng(result)
  latitude.value = lat
  longitude.value = lng
}
</script>

<template>
  <v-autocomplete
    v-bind="$attrs"
    v-model:search="query"
    :model-value="query"
    return-object
    auto-complete="off"
    :items="suggestions"
    item-title="description"
    item-value="description"
    :loading="loading"
    no-data-text="No Results Found"
    @update:model-value="setPlace"
  />
</template>

<style scoped lang="scss">

</style>

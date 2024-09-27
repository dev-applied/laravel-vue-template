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

const model = defineModel<null | string>({
  required: true,
})
const latitude = defineModel<null | number>('latitude')
const longitude = defineModel<null | number>('longitude')
const parts = defineModel<{
  address_line_1: string;
  city: string;
  state: string;
  country: string;
  zipcode: string;
}>('parts')

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
    latitude.value = null
    longitude.value = null
    return
  }
  const results = await geocodeByPlaceId(suggestion.place_id)
  const result = results[0]
  const {lat, lng} = await getLatLng(result)
  model.value = suggestion.description
  latitude.value = lat
  longitude.value = lng
  parts.value = {
    address_line_1: findByType('street_number', result).long_name + ' ' + findByType('route', result).long_name,
    city: findByType('locality', result).long_name,
    state: findByType('administrative_area_level_1', result).long_name,
    country: findByType('country', result).long_name,
    zipcode: findByType('postal_code', result).long_name,
  }
}


</script>

<template>
  <v-autocomplete
    v-bind="$attrs"
    v-model:search="query"
    v-model="query"
    @update:model-value="setPlace"
    return-object
    :items="suggestions"
    item-title="description"
    item-value="description"
    :loading="loading"
    no-data-text="No Results Found"
  />
</template>

<style scoped lang="scss">

</style>

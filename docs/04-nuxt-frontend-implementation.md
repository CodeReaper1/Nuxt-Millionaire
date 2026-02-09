# Nuxt Frontend Implementation - Using Econt with WooNuxt

## What This Document Covers

This shows you how to build the customer-facing part of your Econt integration in your Nuxt.js frontend. This is what users actually see and interact with.

## Project Structure

```
your-nuxt-app/
├── composables/
│   ├── useEcont.ts              # Main Econt logic
│   └── useEcontOffices.ts       # Office selector logic
├── components/
│   ├── EcontOfficeSelector.vue  # Office picker component
│   ├── EcontShippingRate.vue    # Shipping calculator
│   └── EcontTracking.vue        # Tracking display
├── graphql/
│   ├── queries/
│   │   ├── getEcontOffices.gql
│   │   ├── getEcontCities.gql
│   │   └── getOrder.gql
│   └── mutations/
│       └── selectEcontOffice.gql
└── pages/
    ├── checkout.vue             # Modified checkout
    └── tracking.vue             # Tracking page
```

## Step 1: GraphQL Configuration

If you haven't already, configure GraphQL client in Nuxt:

**File: `nuxt.config.ts`**

```typescript
export default defineNuxtConfig({
  modules: [
    '@nuxtjs/apollo', // or your GraphQL module
  ],
  
  apollo: {
    clients: {
      default: {
        httpEndpoint: process.env.GRAPHQL_ENDPOINT || 'https://yoursite.com/graphql',
      }
    }
  },
  
  runtimeConfig: {
    public: {
      graphqlEndpoint: process.env.GRAPHQL_ENDPOINT || 'https://yoursite.com/graphql',
    }
  }
})
```

## Step 2: GraphQL Queries

**File: `graphql/queries/getEcontOffices.gql`**

```graphql
query GetEcontOffices($city: String, $search: String) {
  econOffices(city: $city, search: $search) {
    id
    code
    name
    address
    city
    postCode
    latitude
    longitude
    workingTimeFrom
    workingTimeTo
    workingTimeHalfFrom
    workingTimeHalfTo
  }
}
```

**File: `graphql/queries/getEcontCities.gql`**

```graphql
query GetEcontCities($search: String) {
  econCities(search: $search) {
    id
    name
    postCode
    regionName
  }
}
```

**File: `graphql/mutations/selectEcontOffice.gql`**

```graphql
mutation SelectEcontOffice(
  $orderId: ID!
  $officeId: String!
  $officeName: String
  $officeAddress: String
  $officeCity: String
) {
  selectEcontOffice(
    input: {
      orderId: $orderId
      officeId: $officeId
      officeName: $officeName
      officeAddress: $officeAddress
      officeCity: $officeCity
    }
  ) {
    success
    message
    order {
      id
      econOffice {
        name
        address
      }
    }
  }
}
```

## Step 3: Econt Composable

**File: `composables/useEcont.ts`**

```typescript
import { ref, computed } from 'vue'

export interface EcontOffice {
  id: string
  code: string
  name: string
  address: string
  city: string
  postCode?: string
  latitude?: number
  longitude?: number
  workingTimeFrom?: string
  workingTimeTo?: string
  workingTimeHalfFrom?: string
  workingTimeHalfTo?: string
}

export interface EcontCity {
  id: string
  name: string
  postCode?: string
  regionName?: string
}

export const useEcont = () => {
  const { $apollo } = useNuxtApp()
  
  const offices = ref<EcontOffice[]>([])
  const cities = ref<EcontCity[]>([])
  const selectedOffice = ref<EcontOffice | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  
  /**
   * Fetch offices by city
   */
  const fetchOffices = async (city?: string, search?: string) => {
    loading.value = true
    error.value = null
    
    try {
      const { data } = await $apollo.query({
        query: gql`
          query GetEcontOffices($city: String, $search: String) {
            econOffices(city: $city, search: $search) {
              id
              code
              name
              address
              city
              postCode
              latitude
              longitude
              workingTimeFrom
              workingTimeTo
              workingTimeHalfFrom
              workingTimeHalfTo
            }
          }
        `,
        variables: { city, search },
      })
      
      offices.value = data.econOffices || []
      return offices.value
    } catch (e: any) {
      error.value = e.message
      console.error('Error fetching offices:', e)
      return []
    } finally {
      loading.value = false
    }
  }
  
  /**
   * Fetch all cities
   */
  const fetchCities = async (search?: string) => {
    loading.value = true
    error.value = null
    
    try {
      const { data } = await $apollo.query({
        query: gql`
          query GetEcontCities($search: String) {
            econCities(search: $search) {
              id
              name
              postCode
              regionName
            }
          }
        `,
        variables: { search },
      })
      
      cities.value = data.econCities || []
      return cities.value
    } catch (e: any) {
      error.value = e.message
      console.error('Error fetching cities:', e)
      return []
    } finally {
      loading.value = false
    }
  }
  
  /**
   * Calculate shipping cost
   */
  const calculateShipping = async (
    weight: number,
    receiverCity: string,
    senderCity?: string,
    codAmount?: number
  ) => {
    loading.value = true
    error.value = null
    
    try {
      const { data } = await $apollo.query({
        query: gql`
          query CalculateShipping(
            $weight: Float!
            $receiverCity: String!
            $senderCity: String
            $codAmount: Float
          ) {
            econCalculateShipping(
              weight: $weight
              receiverCity: $receiverCity
              senderCity: $senderCity
              codAmount: $codAmount
            )
          }
        `,
        variables: {
          weight,
          receiverCity,
          senderCity,
          codAmount,
        },
      })
      
      return data.econCalculateShipping
    } catch (e: any) {
      error.value = e.message
      console.error('Error calculating shipping:', e)
      return null
    } finally {
      loading.value = false
    }
  }
  
  /**
   * Select office for order
   */
  const selectOffice = async (orderId: string, office: EcontOffice) => {
    loading.value = true
    error.value = null
    
    try {
      const { data } = await $apollo.mutate({
        mutation: gql`
          mutation SelectEcontOffice(
            $orderId: ID!
            $officeId: String!
            $officeName: String
            $officeAddress: String
            $officeCity: String
          ) {
            selectEcontOffice(
              input: {
                orderId: $orderId
                officeId: $officeId
                officeName: $officeName
                officeAddress: $officeAddress
                officeCity: $officeCity
              }
            ) {
              success
              message
              order {
                id
                econOffice {
                  name
                  address
                }
              }
            }
          }
        `,
        variables: {
          orderId,
          officeId: office.code || office.id,
          officeName: office.name,
          officeAddress: office.address,
          officeCity: office.city,
        },
      })
      
      if (data.selectEcontOffice.success) {
        selectedOffice.value = office
        return true
      } else {
        error.value = data.selectEcontOffice.message
        return false
      }
    } catch (e: any) {
      error.value = e.message
      console.error('Error selecting office:', e)
      return false
    } finally {
      loading.value = false
    }
  }
  
  /**
   * Get tracking info for order
   */
  const getTracking = async (orderId: string) => {
    loading.value = true
    error.value = null
    
    try {
      const { data } = await $apollo.query({
        query: gql`
          query GetOrderTracking($orderId: ID!) {
            order(id: $orderId, idType: DATABASE_ID) {
              id
              econTrackingNumber
              econLabelUrl
              econTracking {
                trackingNumber
                status
                statusDescription
                location
                estimatedDelivery
                labelUrl
              }
            }
          }
        `,
        variables: { orderId },
      })
      
      return data.order
    } catch (e: any) {
      error.value = e.message
      console.error('Error fetching tracking:', e)
      return null
    } finally {
      loading.value = false
    }
  }
  
  return {
    // State
    offices,
    cities,
    selectedOffice,
    loading,
    error,
    
    // Methods
    fetchOffices,
    fetchCities,
    calculateShipping,
    selectOffice,
    getTracking,
  }
}
```

## Step 4: Office Selector Component

**File: `components/EcontOfficeSelector.vue`**

```vue
<template>
  <div class="econt-office-selector">
    <h3>Изберете офис на Еконт</h3>
    
    <!-- City selector -->
    <div class="mb-4">
      <label for="city-select" class="block text-sm font-medium mb-2">
        Град
      </label>
      <select
        id="city-select"
        v-model="selectedCity"
        @change="onCityChange"
        class="w-full p-2 border rounded"
      >
        <option value="">Изберете град...</option>
        <option v-for="city in cities" :key="city.id" :value="city.name">
          {{ city.name }}
        </option>
      </select>
    </div>
    
    <!-- Search -->
    <div class="mb-4">
      <label for="office-search" class="block text-sm font-medium mb-2">
        Търсене
      </label>
      <input
        id="office-search"
        v-model="searchQuery"
        type="text"
        placeholder="Търси по адрес или име..."
        class="w-full p-2 border rounded"
        @input="onSearch"
      />
    </div>
    
    <!-- Loading state -->
    <div v-if="loading" class="text-center py-8">
      <div class="spinner">Зареждане...</div>
    </div>
    
    <!-- Error state -->
    <div v-else-if="error" class="bg-red-100 text-red-700 p-4 rounded mb-4">
      {{ error }}
    </div>
    
    <!-- Offices list -->
    <div v-else-if="filteredOffices.length > 0" class="offices-list max-h-96 overflow-y-auto">
      <div
        v-for="office in filteredOffices"
        :key="office.id"
        class="office-item p-4 border rounded mb-2 cursor-pointer hover:bg-gray-50 transition"
        :class="{ 'bg-blue-50 border-blue-500': isSelected(office) }"
        @click="selectCurrentOffice(office)"
      >
        <div class="font-medium">{{ office.name }}</div>
        <div class="text-sm text-gray-600">{{ office.address }}</div>
        <div class="text-sm text-gray-500 mt-1">
          {{ office.city }}{{ office.postCode ? `, ${office.postCode}` : '' }}
        </div>
        <div v-if="office.workingTimeFrom" class="text-xs text-gray-500 mt-1">
          Работно време: {{ office.workingTimeFrom }} - {{ office.workingTimeTo }}
          <span v-if="office.workingTimeHalfFrom">
            | Събота: {{ office.workingTimeHalfFrom }} - {{ office.workingTimeHalfTo }}
          </span>
        </div>
      </div>
    </div>
    
    <!-- Empty state -->
    <div v-else class="text-center py-8 text-gray-500">
      <p>Няма намерени офиси.</p>
      <p class="text-sm mt-2">Опитайте да изберете друг град или промените търсенето.</p>
    </div>
    
    <!-- Selected office summary -->
    <div v-if="selectedOffice" class="mt-4 p-4 bg-green-50 border border-green-200 rounded">
      <div class="flex justify-between items-start">
        <div>
          <div class="font-medium text-green-800">✓ Избран офис</div>
          <div class="text-sm mt-1">{{ selectedOffice.name }}</div>
          <div class="text-sm text-gray-600">{{ selectedOffice.address }}</div>
        </div>
        <button
          @click="clearSelection"
          class="text-red-600 hover:text-red-800 text-sm"
        >
          Промени
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import type { EcontOffice } from '~/composables/useEcont'

const props = defineProps<{
  modelValue?: EcontOffice | null
  orderId?: string
  autoSave?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [office: EcontOffice | null]
  'office-selected': [office: EcontOffice]
}>()

const {
  offices,
  cities,
  selectedOffice,
  loading,
  error,
  fetchOffices,
  fetchCities,
  selectOffice,
} = useEcont()

const selectedCity = ref('')
const searchQuery = ref('')

// Load cities on mount
onMounted(async () => {
  await fetchCities()
})

// Filter offices by search query
const filteredOffices = computed(() => {
  if (!searchQuery.value) return offices.value
  
  const query = searchQuery.value.toLowerCase()
  return offices.value.filter(office => {
    const searchable = `${office.name} ${office.address}`.toLowerCase()
    return searchable.includes(query)
  })
})

// City change handler
const onCityChange = async () => {
  if (selectedCity.value) {
    await fetchOffices(selectedCity.value)
  } else {
    offices.value = []
  }
}

// Search handler with debounce
let searchTimeout: NodeJS.Timeout
const onSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    if (searchQuery.value.length >= 2) {
      await fetchOffices(selectedCity.value, searchQuery.value)
    }
  }, 300)
}

// Check if office is selected
const isSelected = (office: EcontOffice) => {
  return selectedOffice.value?.id === office.id
}

// Select office
const selectCurrentOffice = async (office: EcontOffice) => {
  selectedOffice.value = office
  emit('update:modelValue', office)
  emit('office-selected', office)
  
  // Auto-save to order if orderId provided
  if (props.autoSave && props.orderId) {
    await selectOffice(props.orderId, office)
  }
}

// Clear selection
const clearSelection = () => {
  selectedOffice.value = null
  emit('update:modelValue', null)
}

// Sync with v-model
watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    selectedOffice.value = newValue
  }
})
</script>

<style scoped>
.spinner {
  display: inline-block;
  width: 20px;
  height: 20px;
  border: 3px solid rgba(0, 0, 0, 0.1);
  border-radius: 50%;
  border-top-color: #3b82f6;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.office-item {
  transition: all 0.2s ease;
}

.office-item:hover {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>
```

## Step 5: Integrate into Checkout

**File: `pages/checkout.vue` (modification)**

```vue
<template>
  <div class="checkout-page">
    <!-- ... other checkout fields ... -->
    
    <!-- Shipping method selector -->
    <div class="shipping-section mb-6">
      <h3>Метод на доставка</h3>
      
      <!-- WooCommerce shipping methods -->
      <div v-for="method in shippingMethods" :key="method.id">
        <label>
          <input
            type="radio"
            v-model="selectedShippingMethod"
            :value="method.id"
            @change="onShippingMethodChange"
          />
          {{ method.label }} - {{ method.cost }} лв.
        </label>
      </div>
    </div>
    
    <!-- Econt office selector (shown only when Econt is selected) -->
    <div v-if="isEcontSelected" class="mb-6">
      <EcontOfficeSelector
        v-model="selectedEcontOffice"
        :order-id="pendingOrderId"
        :auto-save="true"
        @office-selected="onOfficeSelected"
      />
    </div>
    
    <!-- ... rest of checkout ... -->
    
    <button
      @click="placeOrder"
      :disabled="!canPlaceOrder"
      class="btn-primary w-full"
    >
      Завършване на поръчката
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { EcontOffice } from '~/composables/useEcont'

const selectedShippingMethod = ref('')
const selectedEcontOffice = ref<EcontOffice | null>(null)
const pendingOrderId = ref<string | null>(null)

// Check if Econt shipping is selected
const isEcontSelected = computed(() => {
  return selectedShippingMethod.value?.includes('econt')
})

// Can place order only if office selected when Econt chosen
const canPlaceOrder = computed(() => {
  if (isEcontSelected.value) {
    return selectedEcontOffice.value !== null
  }
  return true // For other shipping methods
})

const onShippingMethodChange = () => {
  // Clear office selection if switching away from Econt
  if (!isEcontSelected.value) {
    selectedEcontOffice.value = null
  }
}

const onOfficeSelected = (office: EcontOffice) => {
  console.log('Office selected:', office)
  // Office is auto-saved via composable
}

const placeOrder = async () => {
  // Your order placement logic
  // The Econt office is already saved via the auto-save feature
}
</script>
```

## Step 6: Tracking Page

**File: `pages/tracking.vue`**

```vue
<template>
  <div class="tracking-page max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Проследяване на пратка</h1>
    
    <!-- Tracking number input -->
    <div class="mb-6">
      <input
        v-model="trackingNumber"
        type="text"
        placeholder="Въведете номер за проследяване..."
        class="w-full p-3 border rounded"
        @keyup.enter="trackShipment"
      />
      <button
        @click="trackShipment"
        class="mt-2 w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700"
      >
        Проследи
      </button>
    </div>
    
    <!-- Loading -->
    <div v-if="loading" class="text-center py-8">
      Зареждане...
    </div>
    
    <!-- Tracking info -->
    <div v-else-if="trackingInfo" class="bg-white shadow rounded-lg p-6">
      <div class="mb-4">
        <h2 class="text-xl font-semibold">Пратка #{{ trackingInfo.econTrackingNumber }}</h2>
      </div>
      
      <div v-if="trackingInfo.econTracking" class="space-y-4">
        <div>
          <span class="font-medium">Статус:</span>
          <span class="ml-2 px-3 py-1 bg-blue-100 text-blue-800 rounded">
            {{ trackingInfo.econTracking.status }}
          </span>
        </div>
        
        <div v-if="trackingInfo.econTracking.statusDescription">
          <span class="font-medium">Описание:</span>
          <p class="mt-1">{{ trackingInfo.econTracking.statusDescription }}</p>
        </div>
        
        <div v-if="trackingInfo.econTracking.location">
          <span class="font-medium">Локация:</span>
          <p class="mt-1">{{ trackingInfo.econTracking.location }}</p>
        </div>
        
        <div v-if="trackingInfo.econTracking.estimatedDelivery">
          <span class="font-medium">Очаквана доставка:</span>
          <p class="mt-1">{{ formatDate(trackingInfo.econTracking.estimatedDelivery) }}</p>
        </div>
        
        <div v-if="trackingInfo.econLabelUrl">
          <a
            :href="trackingInfo.econLabelUrl"
            target="_blank"
            class="text-blue-600 hover:underline"
          >
            Изтегли товарителница (PDF)
          </a>
        </div>
      </div>
      
      <div v-else class="text-gray-500">
        Няма информация за проследяване.
      </div>
    </div>
    
    <!-- Error -->
    <div v-else-if="error" class="bg-red-100 text-red-700 p-4 rounded">
      {{ error }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const trackingNumber = ref('')
const trackingInfo = ref(null)
const loading = ref(false)
const error = ref<string | null>(null)

const { getTracking } = useEcont()

const trackShipment = async () => {
  if (!trackingNumber.value) {
    error.value = 'Моля, въведете номер за проследяване'
    return
  }
  
  loading.value = true
  error.value = null
  trackingInfo.value = null
  
  try {
    const result = await getTracking(trackingNumber.value)
    if (result) {
      trackingInfo.value = result
    } else {
      error.value = 'Не е намерена информация за тази пратка'
    }
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('bg-BG', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}
</script>
```

---

## 🧠 ADHD-FRIENDLY SUMMARY

**What did we build?**
The customer-facing parts of your Econt integration - what people actually see and click.

**Three main pieces:**
1. **Composable** (`useEcont.ts`) = The brain - handles all the logic
2. **Office Selector** (`EcontOfficeSelector.vue`) = The UI - lets customers pick a location
3. **Checkout Integration** = Plugs into your existing checkout

**How it flows:**
1. Customer adds items to cart
2. Goes to checkout
3. Selects "Econt to Office" shipping
4. **BOOM** - office selector appears
5. Types their city (e.g., "София")
6. Picks an office from the list
7. Office auto-saves to their order
8. Completes checkout

**The composable is your friend:**
It's like a toolbox with functions:
- `fetchOffices()` = Get all pickup points
- `selectOffice()` = Save customer's choice
- `getTracking()` = Show where package is

**Key feature: Auto-save**
When customer picks an office, it automatically saves to their order in WordPress. No extra button needed!

**Testing tip:**
1. Start with just the office selector component
2. Test it standalone before adding to checkout
3. Console.log everything to see what's happening

**Next step:** Test everything end-to-end and fix bugs (see testing document)

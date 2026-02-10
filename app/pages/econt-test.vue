<template>
  <div class="econt-test container">
    <h1>Econt integration test</h1>

    <section>
      <h2>Cities</h2>
      <div style="display:flex;gap:8px;align-items:center">
        <input v-model="citySearch" placeholder="Search city" />
        <button @click="loadCities">Fetch cities</button>
        <div v-if="loading">Loading...</div>
      </div>
      <div style="margin-top:8px">
        <label for="city-select">Choose city:</label>
        <select id="city-select" v-model="selectedCity" @change="onCityChange">
          <option value="">-- Select a city --</option>
          <option v-for="c in cities" :key="c.id" :value="c.name">{{ c.name }}{{ c.postCode ? (' — ' + c.postCode) : '' }}</option>
        </select>
      </div>
    </section>

    <section>
      <h2>Offices (selected city: {{ selectedCity || '-' }})</h2>
      <div style="display:flex;gap:8px;align-items:center">
        <input v-model="officeSearch" placeholder="Search offices" />
        <button @click="loadOffices">Fetch offices</button>
      </div>
      <div style="margin-top:8px">
        <label for="office-select">Choose office:</label>
        <select id="office-select" v-model="selectedOffice" @change="onOfficeChange">
          <option :value="null">-- Select an office --</option>
          <option v-for="o in offices" :key="o.id" :value="o">{{ o.name }} — {{ o.address }}</option>
        </select>
      </div>
    </section>

    <section>
      <h2>Offices map</h2>
      <EcontMap :offices="offices" :selectedOffice="selectedOffice" @select-office="selectOffice" />
    </section>

    <section>
      <h2>Selected office</h2>
      <div v-if="selectedOffice">
        <p><strong>{{ selectedOffice.name }}</strong></p>
        <p>{{ selectedOffice.address }}, {{ selectedOffice.city }} {{ selectedOffice.postCode }}</p>
      </div>
      <div v-else>No office selected</div>
    </section>

    <section>
      <h2>Calculate shipping</h2>
      <label>Weight (kg): <input v-model.number="weight" type="number" step="0.1" /></label>
      <label>Receiver city: <input v-model="receiverCity" /></label>
      <label>Sender city: <input v-model="senderCity" /></label>
      <label>COD amount (BGN): <input v-model.number="codAmount" type="number" step="0.01" /></label>
      <button @click="calculate">Calculate</button>
      <div v-if="shippingResult">
        <p>Price: {{ shippingResult.price }} {{ shippingResult.currency }}</p>
        <p>Delivery days: {{ shippingResult.deliveryDays }}</p>
      </div>
      <div v-if="calcError" style="color:crimson">{{ calcError }}</div>
    </section>

    <section>
      <h2>Save office to order (fallback)</h2>
      <label>Order ID: <input v-model.number="orderId" type="number" /></label>
      <button @click="saveToOrder" :disabled="!selectedOffice || !orderId">Save office to order</button>
      <div v-if="saveResult">{{ saveResult }}</div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useEcont } from '../composables/useEcont'
import EcontMap from '../components/EcontMap.vue'

const { cities, offices, selectedOffice, selectedCity, loading, error, fetchCities, fetchOffices, selectOffice, saveOfficeToOrder } = useEcont()

// Local UI state
const citySearch = ref('')
const officeSearch = ref('')
const weight = ref(1.0)
const receiverCity = ref('')
const senderCity = ref('')
const codAmount = ref(0)
const shippingResult = ref<any | null>(null)
const calcError = ref<string | null>(null)
const orderId = ref<number | null>(null)
const saveResult = ref<string | null>(null)

const selectedOfficeLocal = computed(() => selectedOffice.value)
const selectedCityLocal = computed(() => selectedCity.value)

async function loadCities() {
  await fetchCities(citySearch.value || undefined)
}

async function selectCity(name: string) {
  // set selected city state and load offices
  selectedCity.value = name
  receiverCity.value = name
  await fetchOffices(name, officeSearch.value || undefined)
}

async function loadOffices() {
  await fetchOffices(selectedCity.value || undefined, officeSearch.value || undefined)
}

async function onCityChange() {
  const name = selectedCity.value
  receiverCity.value = name
  await fetchOffices(name || undefined, officeSearch.value || undefined)
}

function onOfficeChange() {
  if (selectedOffice.value) {
    selectOffice(selectedOffice.value)
  }
}

// Use composable's selectOffice directly; wrapper removed to avoid name conflict

async function calculate() {
  calcError.value = null
  shippingResult.value = null

  if (!receiverCity.value) {
    calcError.value = 'Receiver city required'
    return
  }

  const q = `query($weight:Float!,$receiverCity:String!,$senderCity:String,$codAmount:Float,$deliveryType:String){ econtCalculateShipping(weight:$weight,receiverCity:$receiverCity,senderCity:$senderCity,codAmount:$codAmount,deliveryType:$deliveryType){ price currency deliveryDays } }`

  try {
    const res = await fetch('/graphql', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ query: q, variables: { weight: weight.value, receiverCity: receiverCity.value, senderCity: senderCity.value || null, codAmount: codAmount.value || 0, deliveryType: 'OFFICE_OFFICE' } })
    })
    const js = await res.json()
    if (js.errors) {
      calcError.value = js.errors.map((e:any)=>e.message).join('; ')
      return
    }
    shippingResult.value = js.data?.econtCalculateShipping ?? null
  } catch (e:any) {
    calcError.value = e.message || String(e)
  }
}

async function saveToOrder() {
  saveResult.value = null
  if (!selectedOffice.value) {
    saveResult.value = 'No office selected'
    return
  }
  if (!orderId.value) {
    saveResult.value = 'Order ID required'
    return
  }

  const ok = await saveOfficeToOrder(orderId.value, selectedOffice.value)
  saveResult.value = ok ? 'Saved successfully' : 'Save failed (check GraphQL/mutation)'
}
</script>

<style scoped>
.econt-test { padding: 20px; }
section { margin-bottom: 24px; padding: 12px; border: 1px solid #eee; }
input { margin-right: 8px; }
button { margin-left: 8px; }
</style>

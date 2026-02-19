<script setup lang="ts">
import type { EcontOffice } from '~/app/composables/useEcont';

const props = defineProps<{
  /** Currently selected office (v-model). */
  modelValue?: EcontOffice | null;
}>();

const emit = defineEmits<{
  'update:modelValue': [office: EcontOffice | null];
  'office-selected': [office: EcontOffice];
}>();

const {
  cities,
  offices,
  selectedOffice,
  selectedCity,
  loading,
  error,
  fetchCities,
  fetchOffices,
  selectOffice,
} = useEcont();

const searchQuery = ref('');
const citiesLoaded = ref(false);

// ── Load cities once on mount ────────────────────────────────────────
onMounted(async () => {
  if (!citiesLoaded.value) {
    await fetchCities();
    citiesLoaded.value = true;
  }
});

// ── Filtered offices (client-side extra filter on top of API results) ─
const filteredOffices = computed(() => {
  if (!searchQuery.value) return offices.value;
  const q = searchQuery.value.toLowerCase();
  return offices.value.filter((o) => {
    const haystack = `${o.name} ${o.address}`.toLowerCase();
    return haystack.includes(q);
  });
});

// ── City change ──────────────────────────────────────────────────────
async function onCityChange() {
  searchQuery.value = '';
  if (selectedCity.value) {
    await fetchOffices(selectedCity.value);
  } else {
    offices.value = [];
  }
}

// ── Search with debounce ─────────────────────────────────────────────
let searchTimeout: ReturnType<typeof setTimeout>;
function onSearchInput() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(async () => {
    if (searchQuery.value.length >= 2) {
      await fetchOffices(selectedCity.value || undefined, searchQuery.value);
    }
  }, 300);
}

// ── Select office ────────────────────────────────────────────────────
function pickOffice(office: EcontOffice) {
  selectOffice(office);
  emit('update:modelValue', office);
  emit('office-selected', office);
}

// ── Clear selection ──────────────────────────────────────────────────
function clearSelection() {
  selectOffice(null);
  emit('update:modelValue', null);
}

// ── Check if office is selected ──────────────────────────────────────
function isSelected(office: EcontOffice) {
  return selectedOffice.value?.code === office.code || selectedOffice.value?.id === office.id;
}

// ── Sync external v-model into internal state ────────────────────────
watch(
  () => props.modelValue,
  (val) => {
    if (val) selectOffice(val);
  },
);
</script>

<template>
  <div class="econt-office-selector">
    <h3 class="text-lg font-semibold mb-4 dark:text-white">Изберете офис на Еконт</h3>

    <!-- City dropdown -->
    <div class="mb-4">
      <label for="econt-city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Град</label>
      <select
        id="econt-city"
        v-model="selectedCity"
        class="w-full p-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md text-sm focus:ring-2 focus:ring-primary focus:border-primary"
        @change="onCityChange">
        <option value="">Изберете град...</option>
        <option v-for="city in cities" :key="city.id" :value="city.name">
          {{ city.name }}
          <template v-if="city.regionName"> ({{ city.regionName }})</template>
        </option>
      </select>
    </div>

    <!-- Search -->
    <div v-if="selectedCity" class="mb-4">
      <label for="econt-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Търсене на офис</label>
      <input
        id="econt-search"
        v-model="searchQuery"
        type="text"
        placeholder="Търси по адрес или име..."
        class="w-full p-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md text-sm focus:ring-2 focus:ring-primary focus:border-primary"
        @input="onSearchInput" />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-8 text-gray-500 dark:text-gray-400">
      <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
      <span>Зареждане...</span>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 p-3 rounded-md mb-4 text-sm">
      {{ error }}
    </div>

    <!-- Office list -->
    <div v-else-if="filteredOffices.length > 0 && !selectedOffice" class="offices-list max-h-80 overflow-y-auto space-y-2">
      <div
        v-for="office in filteredOffices"
        :key="office.code || office.id"
        class="office-item p-3 border rounded-md cursor-pointer transition-all hover:shadow-sm"
        :class="
          isSelected(office)
            ? 'bg-primary/5 border-primary/50 dark:bg-primary/10'
            : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 bg-white dark:bg-gray-700'
        "
        @click="pickOffice(office)">
        <div class="font-medium text-sm text-gray-900 dark:text-white">{{ office.name }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-400 mt-0.5">{{ office.address }}</div>
        <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
          {{ office.city }}
          <template v-if="office.postCode">, {{ office.postCode }}</template>
        </div>
        <div v-if="office.workingTimeFrom" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
          Пн-Пт: {{ office.workingTimeFrom }} - {{ office.workingTimeTo }}
          <template v-if="office.workingTimeHalfFrom">
            | Сб: {{ office.workingTimeHalfFrom }} - {{ office.workingTimeHalfTo }}
          </template>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="selectedCity && !selectedOffice && !loading" class="text-center py-6 text-gray-500 dark:text-gray-400">
      <p>Няма намерени офиси.</p>
      <p class="text-sm mt-1">Опитайте да изберете друг град или да промените търсенето.</p>
    </div>

    <!-- Selected office summary -->
    <div v-if="selectedOffice" class="mt-2 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md">
      <div class="flex justify-between items-start">
        <div>
          <div class="flex items-center gap-1.5 font-medium text-green-800 dark:text-green-400 text-sm">
            <Icon name="ion:checkmark-circle" size="16" />
            Избран офис
          </div>
          <div class="text-sm text-gray-900 dark:text-white mt-1">{{ selectedOffice.name }}</div>
          <div class="text-sm text-gray-600 dark:text-gray-400">{{ selectedOffice.address }}, {{ selectedOffice.city }}</div>
        </div>
        <button
          type="button"
          class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 underline"
          @click="clearSelection">
          Промени
        </button>
      </div>
    </div>
  </div>
</template>

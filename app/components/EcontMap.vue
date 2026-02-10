<template>
  <div ref="mapContainer" class="econt-map"></div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'

const props = defineProps({
  offices: { type: Array as () => any[], default: () => [] },
  selectedOffice: { type: Object as () => any | null, default: null },
})

const emit = defineEmits(['select-office'])

const mapContainer = ref<HTMLElement | null>(null)
let map: any = null
let markersLayer: any = null

onMounted(async () => {
  // Load Leaflet only on the client to avoid SSR errors (window is not defined)
  if (!mapContainer.value) return
  const leaflet = await import('leaflet')
  // import CSS dynamically so it's not processed on server
  await import('leaflet/dist/leaflet.css')
  const L = (leaflet && (leaflet.default || leaflet)) as any

  map = L.map(mapContainer.value).setView([42.7, 23.3], 7)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map)

  markersLayer = L.layerGroup().addTo(map)
  renderMarkers()
})

onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
  }
})

watch(() => props.offices, () => renderMarkers(), { deep: true })

watch(() => props.selectedOffice, (n) => {
  if (n && map) {
    const lat = n.latitude ?? n.lat ?? null
    const lng = n.longitude ?? n.lng ?? null
    if (lat && lng) map.setView([lat, lng], 13)
  }
})

function renderMarkers() {
  if (!markersLayer || !map) return
  markersLayer.clearLayers()
  const bounds: L.LatLngExpression[] = []

  props.offices.forEach((o: any) => {
    const lat = o.latitude
    const lng = o.longitude
    if (lat == null || lng == null) return

    const marker = L.circleMarker([lat, lng], {
      radius: 6,
      color: '#1565c0',
      fillColor: '#1976d2',
      fillOpacity: 0.9,
    })

    const title = (o.name || o.code || 'Office')
    const addr = o.address || ''
    marker.bindPopup(`<strong>${escapeHtml(title)}</strong><br/>${escapeHtml(addr)}`)
    marker.on('click', () => emit('select-office', o))
    marker.addTo(markersLayer!)
    bounds.push([lat, lng])
  })

  if (bounds.length && map) {
    try { map.fitBounds(bounds as any, { maxZoom: 15 }) } catch (e) { /* ignore */ }
  }
}

function escapeHtml(s = '') {
  const str = String(s)
  const map: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }
  return str.replace(/[&<>"']/g, (c) => map[c] || c)
}
</script>

<style scoped>
.econt-map { width: 100%; height: 420px; border: 1px solid #e6e6e6; border-radius: 4px; }
</style>

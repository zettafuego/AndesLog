<template lang="pug">
.terminal
  .page-head
    div
      h1 Terminal
      p Lector en cuña, botones grandes y cola si la VPN se cae en el pasillo.
  .modes
    v-btn(
      v-for="item in modes"
      :key="item.id"
      :color="mode === item.id ? 'primary' : undefined"
      :variant="mode === item.id ? 'flat' : 'tonal'"
      size="large"
      @click="mode = item.id"
    ) {{ item.label }}
  v-alert.my-3(v-if="error" type="error") {{ error }}
  .panel.mb-3
    template(v-if="mode === 'receive'")
      v-select.mb-3(v-model="orderId" :items="orders" item-title="number" item-value="id" label="ASN")
      ScanField(ref="scan" label="Bulto" @scan="onReceive")
    template(v-else-if="mode === 'pick'")
      v-select.mb-3(v-model="pickId" :items="picks" item-title="number" item-value="id" label="Oleada")
      ScanField(ref="scan" label="Picking" @scan="onPick")
    template(v-else)
      v-select.mb-3(v-model="tripId" :items="shipments" item-title="number" item-value="id" label="Viaje")
      p.text-medium-emphasis Escanea el código del viaje o pulsa la salida. El número del viaje es el código de la puerta.
      ScanField.mt-2(ref="scan" label="Código de viaje" @scan="onTripScan")
      .row-actions.mt-3(v-if="currentTrip")
        v-btn(
          v-for="next in currentTrip.transitions"
          :key="next"
          size="large"
          :color="next === 'exception' ? 'error' : 'primary'"
          @click="move(next)"
        ) {{ next }}
  .result(:class="resultKind")
    h2 {{ resultTitle }}
    p {{ resultBody }}
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import ScanField from '../components/ScanField.vue'
import { api, idempotencyKey } from '../api'

const modes = [
  { id: 'receive', label: 'Recibir' },
  { id: 'pick', label: 'Picar' },
  { id: 'load', label: 'Cargar' },
]
const mode = ref('receive')
const orders = ref([])
const picks = ref([])
const shipments = ref([])
const orderId = ref(null)
const pickId = ref(null)
const tripId = ref(null)
const error = ref('')
const scan = ref(null)
const resultKind = ref('')
const resultTitle = ref('Listo para leer')
const resultBody = ref('Apunta el lector al código. La terminal trata una ráfaga rápida como lectura de pistola.')

const currentTrip = computed(() => shipments.value.find((trip) => trip.id === tripId.value) || null)

function show(kind, title, body) {
  resultKind.value = kind
  resultTitle.value = title
  resultBody.value = body
}

async function boot() {
  const [inbound, pick, fleet] = await Promise.all([api('/inbound'), api('/picks'), api('/shipments')])
  orders.value = inbound.orders
  picks.value = pick.orders
  shipments.value = fleet.shipments
  orderId.value = inbound.orders[0]?.id ?? null
  pickId.value = pick.orders[0]?.id ?? null
  tripId.value = fleet.shipments[0]?.id ?? null
}

async function onReceive({ value, source }) {
  error.value = ''
  try {
    const data = await api(`/inbound/${orderId.value}/receive`, {
      method: 'POST',
      body: { barcode: value, qty: 1, location_code: 'REC-01' },
      idempotency: idempotencyKey(),
    })
    show('ok', data.replayed ? 'Ya registrado' : 'Recibido', `${data.line.sku_code} × ${data.line.added} · ${source}`)
    await boot()
  } catch (err) {
    error.value = ''
    show('bad', 'Rechazado', err.message)
  }
}

async function onPick({ value }) {
  try {
    const data = await api(`/picks/${pickId.value}/confirm`, {
      method: 'POST',
      body: { barcode: value, qty: 1 },
      idempotency: idempotencyKey(),
    })
    show('ok', data.replayed ? 'Ya picado' : 'Picado', `${data.line.sku_code} en ${data.line.location_code}`)
    await boot()
  } catch (err) {
    show('bad', 'Rechazado', err.message)
  }
}

async function onTripScan({ value }) {
  const match = shipments.value.find((trip) => trip.number.toLowerCase() === value.toLowerCase())
  if (!match) {
    show('bad', 'Viaje desconocido', value)
    return
  }
  tripId.value = match.id
  show('ok', match.number, `${match.destination_city} · ${match.status}`)
}

async function move(status) {
  if (!currentTrip.value) return
  try {
    const data = await api(`/shipments/${currentTrip.value.id}/transition`, {
      method: 'POST',
      body: { status },
      idempotency: idempotencyKey(),
    })
    show('ok', data.shipment.number, `Estado ${data.shipment.status}`)
    await boot()
  } catch (err) {
    show('bad', 'No avanzó', err.message)
  }
}

onMounted(async () => {
  try {
    await boot()
  } catch (err) {
    show('bad', 'Sin API', err.message)
  }
})
</script>

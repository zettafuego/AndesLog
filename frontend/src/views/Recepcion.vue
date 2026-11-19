<template lang="pug">
.page
  .page-head
    div
      h1 Recepción
      p Confirma cada bulto contra el ASN. El lector de código entra como teclado.
    v-btn(variant="tonal" prepend-icon="mdi-file-upload-outline" @click="pickFile") Cargar CSV
    input(ref="file" type="file" accept=".csv,text/csv" hidden @change="upload")
  v-alert.mb-3(v-if="error" type="error") {{ error }}
  .two
    .panel
      h2 ASN abierto
      v-select.mb-3(
        v-model="orderId"
        :items="orders"
        item-title="number"
        item-value="id"
        label="Orden de entrada"
      )
      table.grid(v-if="current")
        thead
          tr
            th SKU
            th Código
            th Esperado
            th Recibido
        tbody
          tr(v-for="line in current.lines" :key="line.id")
            td {{ line.sku_name }}
            td.mono {{ line.barcode }}
            td {{ line.qty_expected }}
            td {{ line.qty_received }}
      p.text-medium-emphasis(v-else) No hay ASN abierto en este almacén.
    .panel
      h2 Lectura
      .stack
        ScanField(ref="scan" label="Código de barras" @scan="onScan")
        v-text-field(v-model.number="qty" type="number" min="1" label="Cantidad")
        v-text-field(v-model="locationCode" label="Ubicación" class="mono")
        .row-actions
          v-btn(color="primary" :loading="busy" @click="manual") Recibir
          v-btn(variant="text" @click="fillDemo") Código de prueba
      p.text-caption.text-medium-emphasis.mt-3(v-if="lastSource") Última entrada: {{ lastSource }}
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import ScanField from '../components/ScanField.vue'
import { api, idempotencyKey } from '../api'

const toast = inject('toast', () => {})
const orders = ref([])
const orderId = ref(null)
const qty = ref(1)
const locationCode = ref('REC-01')
const error = ref('')
const busy = ref(false)
const lastSource = ref('')
const scan = ref(null)
const file = ref(null)
const pendingCode = ref('')

const current = computed(() => orders.value.find((order) => order.id === orderId.value) || null)

async function load() {
  const data = await api('/inbound')
  orders.value = data.orders
  if (!orderId.value && data.orders[0]) orderId.value = data.orders[0].id
}

async function receive(barcode) {
  if (!orderId.value) {
    error.value = 'Elige un ASN.'
    return
  }
  error.value = ''
  busy.value = true
  try {
    const data = await api(`/inbound/${orderId.value}/receive`, {
      method: 'POST',
      body: { barcode, qty: Number(qty.value) || 1, location_code: locationCode.value.trim() },
      idempotency: idempotencyKey(),
    })
    toast(data.replayed ? 'Esa lectura ya estaba registrada.' : `Recibido ${data.line.sku_code} × ${data.line.added}`)
    await load()
  } catch (err) {
    error.value = err.message
  } finally {
    busy.value = false
    scan.value?.focus()
  }
}

function onScan({ value, source }) {
  pendingCode.value = value
  lastSource.value = source === 'wedge' ? 'lector (cuña de teclado)' : 'teclado'
  receive(value)
}

function manual() {
  const value = pendingCode.value
  if (!value) {
    error.value = 'Escanea un código primero.'
    scan.value?.focus()
    return
  }
  receive(value)
}

function fillDemo() {
  const line = current.value?.lines?.find((item) => item.qty_received < item.qty_expected)
  if (!line) return
  pendingCode.value = line.barcode
  scan.value?.set(line.barcode)
  scan.value?.focus()
}

function pickFile() {
  file.value?.click()
}

async function upload(event) {
  const selected = event.target.files?.[0]
  event.target.value = ''
  if (!selected) return
  error.value = ''
  const body = new FormData()
  body.append('file', selected)
  try {
    const data = await api('/etl/recepciones', { method: 'POST', body, idempotency: idempotencyKey() })
    toast(`CSV procesado: ${data.created} ASN nuevos, ${data.lines} líneas.`)
    await load()
  } catch (err) {
    error.value = err.message
  }
}

onMounted(async () => {
  try {
    await load()
  } catch (err) {
    error.value = err.message
  }
  scan.value?.focus()
})
</script>

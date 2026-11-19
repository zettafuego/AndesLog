<template lang="pug">
.page
  .page-head
    div
      h1 Picking
      p Confirma la ubicación y el código. No deja picar unidades ya reservadas por otro pedido.
    v-select(
      v-model="orderId"
      :items="orders"
      item-title="number"
      item-value="id"
      label="Oleada"
      style="max-width: 280px"
    )
  v-alert.mb-3(v-if="error" type="error") {{ error }}
  .two
    .panel
      h2 Líneas
      table.grid(v-if="current")
        thead
          tr
            th Ubicación
            th SKU
            th Pedido
            th Picado
        tbody
          tr(v-for="line in current.lines" :key="line.id")
            td.mono {{ line.location_code }}
            td
              .mono {{ line.barcode }}
              div {{ line.sku_name }}
            td {{ line.qty_required }}
            td {{ line.qty_picked }}
      p(v-else) No hay oleadas abiertas.
    .panel
      h2 Confirmar
      .stack
        ScanField(ref="scan" label="Código picado" @scan="onScan")
        v-text-field(v-model.number="qty" type="number" min="1" label="Cantidad")
        v-btn(variant="text" @click="fillDemo") Siguiente pendiente
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import ScanField from '../components/ScanField.vue'
import { api, idempotencyKey } from '../api'

const toast = inject('toast', () => {})
const orders = ref([])
const orderId = ref(null)
const qty = ref(1)
const error = ref('')
const scan = ref(null)
const current = computed(() => orders.value.find((order) => order.id === orderId.value) || null)

async function load() {
  const data = await api('/picks')
  orders.value = data.orders
  if (!orders.value.some((order) => order.id === orderId.value)) orderId.value = data.orders[0]?.id ?? null
}

async function confirm(barcode) {
  if (!orderId.value) {
    error.value = 'No hay oleada.'
    return
  }
  error.value = ''
  try {
    const data = await api(`/picks/${orderId.value}/confirm`, {
      method: 'POST',
      body: { barcode, qty: Number(qty.value) || 1 },
      idempotency: idempotencyKey(),
    })
    toast(data.replayed ? 'Esa confirmación ya estaba aplicada.' : `Picado ${data.line.sku_code} × ${data.line.added}`)
    await load()
  } catch (err) {
    error.value = err.message
  } finally {
    scan.value?.focus()
  }
}

function onScan({ value }) {
  confirm(value)
}

function fillDemo() {
  const line = current.value?.lines?.find((item) => item.qty_picked < item.qty_required)
  if (!line) return
  scan.value?.set(line.barcode)
  scan.value?.focus()
}

onMounted(async () => {
  try {
    await load()
  } catch (err) {
    error.value = err.message
  }
})
</script>

<template lang="pug">
.page
  .page-head
    div
      h1 Transporte
      p El estado del viaje solo avanza por las transiciones permitidas. Un doble toque con la misma clave no mueve el camión dos veces.
    v-btn(variant="text" prepend-icon="mdi-refresh" @click="load") Actualizar
  v-alert.mb-3(v-if="error" type="error") {{ error }}
  .stack
    .panel(v-for="trip in shipments" :key="trip.id")
      .page-head(style="margin-bottom: 8px")
        div
          h2.mono(style="margin:0") {{ trip.number }}
          p.text-medium-emphasis(style="margin:4px 0 0") {{ trip.destination_name }} · {{ trip.destination_city }} · {{ trip.driver }} · {{ trip.plate }}
        span.tag(:class="tag(trip.status)") {{ trip.status }}
      .row-actions
        v-btn(
          v-for="next in trip.transitions"
          :key="next"
          :color="next === 'exception' ? 'error' : 'primary'"
          :variant="next === 'exception' ? 'tonal' : 'flat'"
          @click="move(trip, next)"
        ) {{ label(next) }}
      table.grid.mt-2
        thead
          tr
            th SKU
            th Cantidad
        tbody
          tr(v-for="line in trip.lines" :key="line.id")
            td {{ line.sku_code }} · {{ line.sku_name }}
            td {{ line.qty }}
    p.text-medium-emphasis(v-if="!shipments.length") Sin viajes para este origen.
</template>

<script setup>
import { inject, onMounted, ref } from 'vue'
import { api, idempotencyKey } from '../api'

const toast = inject('toast', () => {})
const shipments = ref([])
const error = ref('')
const labels = {
  loading: 'Pasar a muelle',
  in_transit: 'Salida a ruta',
  delivered: 'Entregado',
  exception: 'Excepción',
  planned: 'Volver a planificado',
}

function label(status) {
  return labels[status] || status
}

function tag(status) {
  if (status === 'delivered') return 'ok'
  if (status === 'exception') return 'bad'
  if (status === 'in_transit' || status === 'loading') return 'warn'
  return 'muted'
}

async function load() {
  error.value = ''
  try {
    const data = await api('/shipments')
    shipments.value = data.shipments
  } catch (err) {
    error.value = err.message
  }
}

async function move(trip, status) {
  error.value = ''
  try {
    const data = await api(`/shipments/${trip.id}/transition`, {
      method: 'POST',
      body: { status },
      idempotency: idempotencyKey(),
    })
    toast(data.replayed ? 'Ese cambio ya se había aplicado.' : `${trip.number} → ${data.shipment.status}`)
    await load()
  } catch (err) {
    error.value = err.message
  }
}

onMounted(load)
</script>

<template lang="pug">
.page
  .page-head
    div
      h1 Tablero de turno
      p {{ board?.warehouse?.name || 'Almacén' }} · generado {{ board?.generated_at || '—' }}
    v-btn(color="primary" prepend-icon="mdi-creation" :loading="briefing" @click="brief") Brief del turno
  v-alert.mb-3(v-if="error" type="error") {{ error }}
  .kpis
    .kpi
      span Recepciones abiertas
      strong {{ board?.kpis?.open_inbound ?? '—' }}
    .kpi
      span Líneas por picar
      strong {{ board?.kpis?.pending_pick_lines ?? '—' }}
    .kpi
      span Despachos en ruta
      strong {{ board?.kpis?.in_transit ?? '—' }}
    .kpi
      span Bajo mínimo
      strong(:class="{ em: (board?.kpis?.below_min || 0) > 0 }") {{ board?.kpis?.below_min ?? '—' }}
  .two
    .panel
      h2 Recepciones
      table.grid
        thead
          tr
            th ASN
            th Proveedor
            th Estado
            th Avance
        tbody
          tr(v-for="order in board?.inbound || []" :key="order.id")
            td.mono {{ order.number }}
            td {{ order.supplier }}
            td
              span.tag(:class="statusClass(order.status)") {{ order.status }}
            td {{ order.received }} / {{ order.expected }}
          tr(v-if="!(board?.inbound || []).length")
            td(colspan="4") Sin recepciones abiertas.
    .panel
      h2 Transporte
      table.grid
        thead
          tr
            th Viaje
            th Destino
            th Estado
            th Placa
        tbody
          tr(v-for="trip in board?.shipments || []" :key="trip.id")
            td.mono {{ trip.number }}
            td {{ trip.destination_city }}
            td
              span.tag(:class="statusClass(trip.status)") {{ trip.status }}
            td.mono {{ trip.plate }}
    .panel
      h2 Bajo mínimo
      table.grid
        thead
          tr
            th SKU
            th Disponible
            th Mínimo
        tbody
          tr(v-for="sku in board?.below_min || []" :key="sku.code")
            td
              .mono {{ sku.code }}
              div {{ sku.name }}
            td {{ sku.available }}
            td {{ sku.min_qty }}
          tr(v-if="!(board?.below_min || []).length")
            td(colspan="3") Ningún SKU bajo el mínimo.
    .panel
      h2 Últimas lecturas
      table.grid
        thead
          tr
            th Código
            th Contexto
            th Resultado
        tbody
          tr(v-for="scan in board?.scans || []" :key="scan.id")
            td.mono {{ scan.barcode }}
            td {{ scan.context }}
            td
              span.tag(:class="scan.result === 'ok' ? 'ok' : 'bad'") {{ scan.result }}
  v-dialog(v-model="openBrief" max-width="640")
    .panel
      h2 Brief {{ briefSource === 'ollama' ? '· Ollama' : '· regla local' }}
      p.brief {{ briefText }}
      v-btn.mt-3(variant="text" @click="openBrief = false") Cerrar
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'

const board = ref(null)
const error = ref('')
const briefing = ref(false)
const openBrief = ref(false)
const briefText = ref('')
const briefSource = ref('')

function statusClass(status) {
  if (['delivered', 'closed'].includes(status)) return 'ok'
  if (['exception'].includes(status)) return 'bad'
  if (['in_transit', 'receiving', 'picking', 'loading'].includes(status)) return 'warn'
  return 'muted'
}

async function load() {
  try {
    board.value = await api('/dashboard')
  } catch (err) {
    error.value = err.message
  }
}

async function brief() {
  briefing.value = true
  error.value = ''
  try {
    const data = await api('/ai/brief', { method: 'POST', body: {} })
    briefText.value = data.brief
    briefSource.value = data.source
    openBrief.value = true
  } catch (err) {
    error.value = err.message
  } finally {
    briefing.value = false
  }
}

onMounted(load)
</script>

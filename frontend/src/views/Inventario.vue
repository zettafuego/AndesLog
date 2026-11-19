<template lang="pug">
.page
  .page-head
    div
      h1 Inventario
      p Saldo por ubicación. La exportación recorre el stock en flujo, sin cargarlo entero en memoria.
    .row-actions
      v-text-field(v-model="query" label="Buscar SKU o ubicación" hide-details style="min-width: 240px" @keyup.enter="load")
      v-btn(variant="tonal" @click="load") Buscar
      v-btn(color="primary" variant="tonal" prepend-icon="mdi-download" @click="exportCsv") CSV
  v-alert.mb-3(v-if="error" type="error") {{ error }}
  .panel
    table.grid
      thead
        tr
          th SKU
          th Ubicación
          th En mano
          th Reservado
          th Disponible
          th
      tbody
        tr(v-for="row in rows" :key="row.id")
          td
            .mono {{ row.sku_code }}
            div {{ row.sku_name }}
          td.mono {{ row.location_code }}
          td {{ row.qty_on_hand }}
          td {{ row.qty_reserved }}
          td
            strong(:class="{ 'text-error': row.available <= 0 }") {{ row.available }}
          td
            v-btn(size="small" variant="text" @click="openReserve(row)") Reservar
        tr(v-if="!rows.length")
          td(colspan="6") Sin saldos para ese filtro.
  v-dialog(v-model="dialog" max-width="420")
    .panel(v-if="selected")
      h2 Reservar {{ selected.sku_code }}
      p.text-medium-emphasis Disponible en {{ selected.location_code }}: {{ selected.available }}. El bloqueo de fila evita que dos terminales reserven de más.
      v-text-field.mt-3(v-model.number="reserveQty" type="number" min="1" :max="selected.available" label="Cantidad")
      v-alert.my-2(v-if="dialogError" type="error") {{ dialogError }}
      .row-actions
        v-btn(color="primary" :loading="busy" @click="reserve") Confirmar
        v-btn(variant="text" @click="dialog = false") Cancelar
</template>

<script setup>
import { inject, onMounted, ref } from 'vue'
import { api, downloadInventory, idempotencyKey } from '../api'

const toast = inject('toast', () => {})
const rows = ref([])
const query = ref('')
const error = ref('')
const dialog = ref(false)
const dialogError = ref('')
const selected = ref(null)
const reserveQty = ref(1)
const busy = ref(false)

async function load() {
  error.value = ''
  try {
    const data = await api(`/inventory?q=${encodeURIComponent(query.value.trim())}`)
    rows.value = data.rows
  } catch (err) {
    error.value = err.message
  }
}

function openReserve(row) {
  selected.value = row
  reserveQty.value = 1
  dialogError.value = ''
  dialog.value = true
}

async function reserve() {
  busy.value = true
  dialogError.value = ''
  try {
    const data = await api('/inventory/reserve', {
      method: 'POST',
      body: { balance_id: selected.value.id, qty: Number(reserveQty.value) },
      idempotency: idempotencyKey(),
    })
    toast(data.replayed ? 'La reserva ya existía.' : `Reservadas ${data.qty} de ${data.sku_code}.`)
    dialog.value = false
    await load()
  } catch (err) {
    dialogError.value = err.message
  } finally {
    busy.value = false
  }
}

async function exportCsv() {
  try {
    await downloadInventory()
  } catch (err) {
    error.value = err.message
  }
}

onMounted(load)
</script>

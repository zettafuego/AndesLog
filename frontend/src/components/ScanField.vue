<template lang="pug">
.scan
  label(:for="fieldId") {{ label }}
  input(
    :id="fieldId"
    ref="input"
    v-model="text"
    autocomplete="off"
    autocorrect="off"
    spellcheck="false"
    :placeholder="placeholder"
    @keydown="onKey"
  )
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  label: { type: String, default: 'Lectura' },
  placeholder: { type: String, default: 'Escanea o escribe y Enter' },
})

const emit = defineEmits(['scan'])
const text = ref('')
const input = ref(null)
const fieldId = `scan-${Math.random().toString(16).slice(2)}`
const gaps = []
let last = 0

function focus() {
  input.value?.focus()
}

function onKey(event) {
  const now = performance.now()
  if (last) gaps.push(now - last)
  last = now
  if (event.key !== 'Enter') return
  event.preventDefault()
  const value = text.value.trim()
  if (!value) return
  const average = gaps.length ? gaps.reduce((a, b) => a + b, 0) / gaps.length : 999
  const source = value.length >= 6 && average < 45 ? 'wedge' : 'manual'
  emit('scan', { value, source })
  text.value = ''
  gaps.length = 0
  last = 0
}

defineExpose({ focus, set(value) { text.value = value } })
</script>

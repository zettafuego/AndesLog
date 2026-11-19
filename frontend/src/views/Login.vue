<template lang="pug">
.login
  section.login-brand
    div
      .mark
        i
        | ANDESLOG
      h1 El turno empieza en el andén.
      p Recepción, inventario, picking y despacho. La misma operación en el navegador, en el escritorio y en la terminal del almacén.
    .bars(aria-hidden="true")
      span(v-for="bar in bars" :key="bar" :style="{ height: bar + 'px' }")
  section.login-form
    h2 Entrar al turno
    p.text-medium-emphasis Elige un operador de demostración o escribe el código de la credencial.
    .ops
      button(v-for="op in operators" :key="op.code" type="button" @click="enter(op)")
        strong.mono {{ op.code }}
        span {{ op.name }} · {{ op.role }}
        em  PIN {{ op.pin }}
    v-form(@submit.prevent="submit")
      .stack
        v-text-field(v-model="code" label="Código" autocomplete="username")
        v-text-field(v-model="pin" label="PIN" type="password" autocomplete="current-password")
        v-alert(v-if="error" type="error") {{ error }}
        v-btn(type="submit" color="primary" size="large" :loading="loading") Entrar
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { useSession } from '../stores/session'

const operators = [
  { code: 'OP-LIMA-01', pin: '1847', name: 'Ana Quispe', role: 'supervisora' },
  { code: 'OP-CASMA-02', pin: '2206', name: 'Luis Rojas', role: 'almacenero' },
]

const bars = [18, 32, 14, 40, 22, 28, 12, 36, 20, 44, 16, 30, 24, 12, 38]
const code = ref('OP-LIMA-01')
const pin = ref('1847')
const error = ref('')
const loading = ref(false)
const router = useRouter()
const session = useSession()

async function submit() {
  error.value = ''
  loading.value = true
  try {
    const data = await api('/auth/login', { method: 'POST', body: { code: code.value.trim(), pin: pin.value } })
    session.open(data.operator, data.token)
    router.push('/')
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

function enter(op) {
  code.value = op.code
  pin.value = op.pin
  submit()
}
</script>

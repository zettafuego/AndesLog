<template lang="pug">
v-app
  template(v-if="!session.user")
    router-view
  template(v-else)
    v-navigation-drawer(
      v-if="!handheld"
      v-model="drawer"
      :permanent="mdAndUp"
      :rail="mdAndUp"
      width="228"
      color="#12151b"
    )
      .pa-3.mark
        i
        | ANDESLOG
      v-list(nav density="compact")
        v-list-item(
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          :prepend-icon="item.icon"
          :title="item.title"
          rounded="lg"
        )
      template(#append)
        .pa-2
          v-btn(block variant="text" prepend-icon="mdi-logout" @click="exit") Salir

    v-app-bar(density="compact" flat border="b" color="#12151b")
      v-app-bar-nav-icon(v-if="!mdAndUp && !handheld" @click="drawer = !drawer")
      .d-flex.align-center.ga-3.px-2.flex-wrap
        strong.mono {{ session.user.warehouse.code }}
        span.text-medium-emphasis {{ session.user.name }}
        span.tag {{ session.user.role }}
        span.tag(:class="session.online ? 'ok' : 'bad'") {{ session.online ? 'en línea' : 'sin red' }}
        span.tag(v-if="session.outbox" class="warn") cola {{ session.outbox }}
        span.tag(v-if="shell === 'electron'" class="ok") escritorio
        span.text-medium-emphasis.mono {{ clockLabel }}
      v-spacer
      v-btn(v-if="!handheld" to="/terminal" variant="tonal" prepend-icon="mdi-barcode-scan") Terminal

    v-main
      router-view

  v-snackbar(v-model="snackOpen" :timeout="2600") {{ snack }}
</template>

<script setup>
import { computed, onMounted, onUnmounted, provide, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useSession } from './stores/session'
import { bindNativeResume, flushOutbox, queueSize } from './api'

const session = useSession()
const route = useRoute()
const router = useRouter()
const { mdAndUp } = useDisplay()
const drawer = ref(false)
const clock = ref(new Date())
const snack = ref('')
const snackOpen = ref(false)
const shell = ref(window.andeslog ? 'electron' : 'web')

const nav = [
  { to: '/', title: 'Tablero', icon: 'mdi-view-dashboard-outline' },
  { to: '/recepcion', title: 'Recepción', icon: 'mdi-package-down' },
  { to: '/inventario', title: 'Inventario', icon: 'mdi-warehouse' },
  { to: '/picking', title: 'Picking', icon: 'mdi-clipboard-check-outline' },
  { to: '/transporte', title: 'Transporte', icon: 'mdi-truck-outline' },
  { to: '/terminal', title: 'Terminal', icon: 'mdi-barcode-scan' },
]

const handheld = computed(() => route.meta.handheld)
const clockLabel = computed(() =>
  new Intl.DateTimeFormat('es-PE', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
    timeZone: 'America/Lima',
  }).format(clock.value),
)

function toast(message) {
  snack.value = message
  snackOpen.value = true
}

provide('toast', toast)

function refreshOutbox() {
  session.outbox = queueSize()
  session.online = navigator.onLine
}

async function drain() {
  refreshOutbox()
  if (!session.user || !session.outbox) return
  const left = await flushOutbox()
  session.outbox = left
  if (left === 0) toast('Cola del terminal sincronizada.')
}

function exit() {
  session.logout()
  router.push('/login')
}

function onOnline() {
  refreshOutbox()
  drain()
}

let clockTimer = 0
let flushTimer = 0

onMounted(() => {
  refreshOutbox()
  clockTimer = window.setInterval(() => { clock.value = new Date() }, 1000)
  flushTimer = window.setInterval(drain, 15000)
  window.addEventListener('online', onOnline)
  window.addEventListener('offline', refreshOutbox)
  window.addEventListener('andeslog-outbox', refreshOutbox)
  bindNativeResume(drain)
})

onUnmounted(() => {
  window.clearInterval(clockTimer)
  window.clearInterval(flushTimer)
  window.removeEventListener('online', onOnline)
  window.removeEventListener('offline', refreshOutbox)
  window.removeEventListener('andeslog-outbox', refreshOutbox)
})

watch(() => route.fullPath, refreshOutbox)
</script>

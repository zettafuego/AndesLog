/**
 * Tarea de fondo de Capacitor Background Runner.
 * El WebView drena la cola real (localStorage). Este runner, cada 15 minutos,
 * despierta un ping al API en la VPN para que el turno no pierda la sesión
 * cuando la terminal está en el bolsillo.
 *
 * Cambia API_HEALTH por el host interno antes de generar el APK.
 */
const API_HEALTH = 'http://127.0.0.1:8000/api/health'

addEventListener('andeslogSync', async (resolve, reject) => {
  try {
    const response = await fetch(API_HEALTH, { method: 'GET' })
    if (!response.ok) {
      reject(new Error('health ' + response.status))
      return
    }
    resolve()
  } catch (error) {
    reject(error)
  }
})

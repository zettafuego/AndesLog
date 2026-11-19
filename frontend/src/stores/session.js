import { defineStore } from 'pinia'
import { setToken } from '../api'

const USER_KEY = 'andeslog-user'

export const useSession = defineStore('session', {
  state: () => ({
    user: JSON.parse(localStorage.getItem(USER_KEY) || 'null'),
    outbox: 0,
    online: typeof navigator === 'undefined' ? true : navigator.onLine,
  }),
  actions: {
    open(operator, token) {
      this.user = operator
      localStorage.setItem(USER_KEY, JSON.stringify(operator))
      setToken(token)
    },
    logout() {
      this.user = null
      localStorage.removeItem(USER_KEY)
      setToken('')
    },
  },
})

import { contextBridge } from 'electron'

contextBridge.exposeInMainWorld('andeslog', {
  shell: 'electron',
  versions: {
    electron: process.versions.electron,
    chrome: process.versions.chrome,
  },
})

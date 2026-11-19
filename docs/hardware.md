# Lectores y terminales

## Pistola en cuña

El lector USB o Bluetooth HID escribe como teclado y cierra con Enter. `ScanField.vue` mide el tiempo entre teclas. Si el código tiene al menos 6 caracteres y el promedio es menor a 45 ms, la lectura se marca como `wedge`. Una digitación humana queda como `manual`. El API no depende de esa marca: guarda el código igual.

La pantalla de Terminal (`/terminal`) usa botones grandes, una sola lectura y un resultado a pantalla completa. Es la vista de la pistola, no la del escritorio del supervisor.

Para probar sin pistola, en Recepción está «Código de prueba». También se puede escribir el EAN y pulsar Enter. Códigos de la semilla:

| Código | SKU |
| --- | --- |
| 7750101000028 | Guante nitrilo (ASN de Lima) |
| 7750101000080 | Grasa EP-2 (ASN de Lima) |
| 7750101000011 | Casco (picking) |
| 7750101000035 | Filtro HF-220 (picking) |
| 7750101000073 | Bota 42 (picking) |

En Carga, el código de la puerta es el número del viaje (`TMS-088`).

## Terminal portátil

Capacitor empaqueta la misma interfaz.

- `capacitor.config.json`: app `art.wafflestech.andeslog`, `webDir` = `dist`.
- La cola offline está en `src/api.js` (`andeslog-outbox-v1`). Un POST que pierde la red, o que recibe 5xx, se guarda con su clave de idempotencia.
- Al volver la red, o al recuperar la app (`@capacitor/app`), la cola se vacía.
- La tarea nativa de fondo no tiene acceso a `localStorage`. Por eso solo hace el ping de salud. El drenado real ocurre con la WebView abierta. Es el límite del Background Runner: no hay que prometer que un escaneo offline se confirma con la pantalla apagada.

Generar el proyecto Android (hace falta el SDK):

```powershell
cd frontend
npm run build
npx cap add android
npx cap sync
npx cap open android
```

En la terminal, el campo de lectura tiene que quedar enfocado. La vista ya lo enfoca al entrar y después de cada recepción o picking.

# Arquitectura

AndesLog es un monolito Laravel con una interfaz Vue aparte. El escritorio y el teléfono no tienen otra lógica de negocio: hablan con el mismo API.

```
frontend (Vue 3, Vite, Pug, Vuetify)  ─┐
desktop  (Electron-Vite, carga :5173) ─┼─►  Laravel /api  ─►  SQLite o SQL Server
Capacitor (WebView + tarea de fondo)  ─┘         │
etl/recepcion_csv.py  ───────────────────────────┤
n8n  ─────────────────────────────────────────────┘
                                              Ollama en localhost:11434
```

## Backend

Laravel 13 sobre PHP 8.3. El requisito era Laravel 8 o superior. Laravel 8 no corre en PHP 8.3 y está fuera de soporte; esta base es la línea actual y conserva los mismos mecanismos: colas, caché, migraciones y `LazyCollection`.

| Pieza | Dónde |
| --- | --- |
| Rutas del turno | `backend/routes/api.php` |
| Reserva, recepción y picking | `app/Services/StockService.php` |
| Exportación en flujo | `app/Services/InventoryExporter.php` (`lazy(200)`) |
| Tablero cacheado 30 s | `app/Services/DashboardQuery.php`, store `database` |
| Invalidación | cada recepción, reserva, picking y cambio de viaje |
| Idempotencia | `EnsureIdempotency` + tabla `idempotency_keys` |
| Sesión de operador | token aleatorio, solo se guarda el SHA-256 |
| Brief | `ShiftBrief`: Ollama y, si no responde, regla local |
| Recorrido de RAM | `php artisan andes:memoria` |

La caché del tablero vive en la tabla `cache` de Laravel. No hace falta Redis para el turno local. En producción el mismo contrato cambia con `CACHE_STORE`.

## Frontend

Vue 3 con Composition API, rutas en `src/router.js` y Pug en las vistas. Vuetify pone densidad de almacén; el layout propio (tablero, tablas, lector) no depende de un tema genérico.

Vite en desarrollo hace de proxy de `/api` hacia el puerto 8000, así el navegador y Electron ven un solo origen. `npm run build:iis` publica la interfaz en `backend/public/app` con base `/app/`.

## Idempotencia

Todo POST que mueve stock o un viaje acepta `Idempotency-Key`. La primera respuesta que no sea 5xx se guarda. Una repetición de la misma clave devuelve el mismo cuerpo y la cabecera `Idempotent-Replay: true`, sin volver a sumar.

La terminal genera la clave antes de llamar. Si la VPN se cae, la cola de `localStorage` reintenta con esa clave. Un 422 también se recuerda: repetir no cambia el intento fallido.

## Memoria

- PHP: `InventoryBalance::lazy(200)` suelta cada lote. El CSV de salida se escribe a `php://output` fila a fila.
- ETL de entrada: un generador lee el CSV con `fgetcsv` y no arma un array con el archivo.
- Python: cada fila se vuelca a un spool por ASN y se cierra el handle. El proceso no acumula las filas.

## Lo que esta copia no incluye

- SQL Server en ejecución. Los scripts están en `backend/database/sqlserver`. El PHP de esta PC no trae `pdo_sqlsrv`.
- Un sitio IIS publicado. El `web.config` ya está en `public`.
- El proyecto Android generado. Capacitor queda configurado; `npx cap add android` crea el nativo.

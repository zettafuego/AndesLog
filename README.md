# AndesLog

Consola de turno para almacén (WMS) y despacho (TMS). La misma API atiende el navegador, la ventana de escritorio y la terminal de mano.

En esta máquina el API corre con SQLite. SQL Server queda preparado (índices, procedimiento con `UPDLOCK, ROWLOCK` y la rama del código que lo usa cuando el driver es `sqlsrv`). No hay un SQL Server instalado aquí.

## Entrar

Operadores de demostración:

| Código | PIN | Almacén | Rol |
| --- | --- | --- | --- |
| OP-LIMA-01 | 1847 | LIM-01 Almacén Central | supervisora |
| OP-CASMA-02 | 2206 | CAS-01 Centro de distribución | almacenero |

## Arrancar

Requisito de esta PC: PHP 8.3 sin `php.ini` global. `scripts/dev.ps1` usa `config/php.ini`, que activa curl, mbstring, openssl y sqlite.

```powershell
cd C:\Users\Eduardo\AndesLog
powershell -ExecutionPolicy Bypass -File .\scripts\setup.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\dev.ps1
```

- API: http://127.0.0.1:8000
- Interfaz: http://127.0.0.1:5173
- Salud: http://127.0.0.1:8000/api/health

`setup.ps1` solo hace falta la primera vez, o después de clonar.

## Qué hace el turno

- Recepción contra un ASN. El lector de código entra como teclado; si las teclas llegan en ráfaga, la pantalla lo marca como cuña.
- Inventario por ubicación, reserva y exportación CSV en flujo (`LazyCollection`).
- Picking que no consume unidades ya reservadas.
- Viajes con transiciones cerradas: planificado, muelle, en ruta, entregado, excepción.
- Si se cae la red, el movimiento POST queda en una cola del navegador y se reintenta con la misma `Idempotency-Key`.
- Brief del turno: Ollama si responde en `127.0.0.1:11434`. En esta PC el modelo configurado es `gemma4`. Si no responde a tiempo, el brief sale de una regla local con las cifras del tablero.

## Escritorio, terminal y ETL

Escritorio (con la interfaz ya en el puerto 5173):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\desktop.ps1
```

CSV de recepción, sin cargar el archivo entero en memoria:

```powershell
python etl\recepcion_csv.py --csv etl\muestras\asn_casma.csv --dry-run
python etl\recepcion_csv.py --csv etl\muestras\asn_casma.csv
```

El ASN entra al almacén del operador. El ejemplo usa a Ana (Lima). Para Casma: `--code OP-CASMA-02 --pin 2206`.

Android, cuando haya SDK:

```powershell
cd frontend
npx cap add android
npx cap sync
```

El runner de fondo está en `frontend/public/background/sync.js`. Antes de generar el APK cambia `API_HEALTH` por el host de la VPN.

n8n: importar `n8n/asn-a-almacen.json`.

Memoria del recorrido de inventario:

```powershell
$env:PHPRC = "C:\Users\Eduardo\AndesLog\config\php.ini"
php artisan andes:memoria
```

(desde `backend`).

## Documentos

- [Arquitectura](docs/arquitectura.md)
- [Operativa WMS y TMS](docs/operativa.md)
- [SQL Server](docs/sql-server.md)
- [IIS y VPN](docs/iis-vpn.md)
- [Lectores y terminales](docs/hardware.md)

## Pruebas

```powershell
$env:PHPRC = "C:\Users\Eduardo\AndesLog\config\php.ini"
cd backend
php artisan test
```

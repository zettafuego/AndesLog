# IIS y VPN

El API se publica dentro de la red del almacén. No se expone a internet. Los teléfonos y los pistoletes salen por la VPN hacia ese host.

## Sitio

1. Instalar IIS, URL Rewrite y CGI.
2. El directorio físico del sitio es `backend/public`. Ahí está `web.config`: manda a `index.php` lo que no sea un archivo.
3. El handler de `*.php` apunta a `php-cgi.exe` de PHP 8.3, con `PHPRC` en la carpeta que contiene el `php.ini` de producción (las mismas extensiones que `config/php.ini`, más `pdo_sqlsrv` si aplica).
4. El pool corre con una identidad que no es administrador. Permiso de lectura en el proyecto y de escritura en `storage` y `bootstrap/cache`.
5. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con el nombre interno (`https://andeslog.local`).

La interfaz de producción se construye dentro del sitio:

```powershell
cd frontend
npm run build:iis
```

Eso deja los estáticos en `backend/public/app`. El proxy de Vite no interviene: el navegador del almacén llama a `/api` en el mismo host.

## VPN

- El grupo de la VPN solo enruta la subred del IIS y, si Ollama vive en otro equipo, el puerto 11434 de ese host. No hace falta túnel completo.
- En el teléfono, `VITE_API_URL` (al construir) apunta a `https://andeslog.local/api`.
- `frontend/public/background/sync.js` usa el mismo host en `API_HEALTH`. La tarea de Capacitor, cada 15 minutos, hace un GET de salud para que la sesión de red no se duerma en el pasillo. La cola de movimientos se drena cuando la WebView vuelve a primer plano.
- El firewall del IIS permite 443 solo desde el pool de direcciones de la VPN.
- Los PIN de demostración se cambian antes de apuntar el sitio a cualquier red, aunque sea privada.

## Caché

`CACHE_STORE=database` alcanza para un solo servidor IIS. Si hay dos nodos detrás del balanceador, los dos tienen que ver la misma base (el store de caché y las claves de idempotencia). Si no, un reintento puede caer en el nodo que no guardó la clave y aplicar el movimiento otra vez.

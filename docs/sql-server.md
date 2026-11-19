# SQL Server

La copia local usa SQLite porque esta PC no tiene el servicio ni el driver `pdo_sqlsrv`. El código de reserva ya distingue el driver.

## Driver

1. Instalar [ODBC Driver 18 for SQL Server](https://learn.microsoft.com/sql/connect/odbc/download-odbc-driver-for-sql-server).
2. Instalar [Microsoft Drivers for PHP for SQL Server](https://learn.microsoft.com/sql/connect/php/download-drivers-php-sql-server) de la misma generación que PHP 8.3, hilo TS x64, y copiar `php_sqlsrv.dll` y `php_pdo_sqlsrv.dll` a la carpeta `ext`.
3. En `config/php.ini`:

```
extension=sqlsrv
extension=pdo_sqlsrv
```

4. En `backend/.env`:

```
DB_CONNECTION=sqlsrv
DB_HOST=10.8.0.12
DB_PORT=1433
DB_DATABASE=AndesLog
DB_USERNAME=andeslog_app
DB_PASSWORD=
```

El usuario de aplicación no debe ser `sa`. Permisos: `SELECT/INSERT/UPDATE` sobre las tablas del turno y `EXECUTE` sobre `dbo.usp_reservar_stock`.

## Después de migrar

```powershell
php artisan migrate --seed --force
sqlcmd -S 10.8.0.12 -d AndesLog -E -i database\sqlserver\001_indices.sql
sqlcmd -S 10.8.0.12 -d AndesLog -E -i database\sqlserver\002_reservar_stock.sql
```

`001_indices.sql` agrega índices de cobertura. `inventory_balances` usa `FILLFACTOR` 80 porque esas filas se reescriben en cada reserva y cada picking. Un fill alto en esa tabla parte páginas en el turno.

`002_reservar_stock.sql` crea `usp_reservar_stock`. El servicio PHP, cuando `DB::getDriverName()` es `sqlsrv`, lee la fila con `UPDLOCK, ROWLOCK` dentro de la transacción y luego hace el `UPDATE` condicional. El procedimiento es el mismo contrato para un job de SQL Agent o para n8n vía un nodo SQL.

`READ COMMITTED SNAPSHOT` puede estar activo para que los tableros no bloqueen a los lectores. No reemplaza el `UPDLOCK`: sin él, dos terminales leen el mismo disponible y las dos creen que pueden reservar. El `UPDATE ... WHERE (qty_on_hand - qty_reserved) >= @qty` es el que impide el exceso si la otra sesión ganó.

## Qué no hacer

No quitar el índice único `(warehouse_id, location_id, sku_id)`. Es el que impide dos saldos para el mismo bulto en el mismo hueco.

# Operativa WMS y TMS

El turno de demostración ya viene cargado. Ana (Lima) ve el día completo. Luis (Casma) ve un ASN y un viaje de retorno.

## Recepción

1. Llega el ASN `ASN-2026-1044` de Andes Repuestos: 40 guantes (10 ya contados) y 12 baldes de grasa.
2. El operador elige el ASN y lee el código. La ubicación por defecto del muelle es `REC-01`.
3. La línea no acepta más de lo esperado. Cuando todas las líneas cierran, el ASN pasa a `closed` y sale de la lista abierta.
4. El saldo de `REC-01` sube y queda un movimiento `receive`.

Un CSV nuevo se carga desde Recepción o con `etl/recepcion_csv.py`. Si el ASN y el SKU ya existen, la línea se omite. Volver a mandar el archivo no duplica.

## Inventario

El disponible es `en mano − reservado`. Reservar bloquea unidades para otro pedido y no las saca del rack.

En SQL Server la reserva toma la fila con `UPDLOCK, ROWLOCK` y solo actualiza si el disponible alcanza. En SQLite el mismo `UPDATE` condicional es la garantía: si no entra, no cambia el saldo.

Bajo mínimo en Lima, con la semilla:

- `GUANTE-NIT`: 16 disponibles, mínimo 40.
- `FILTRO-HF220`: 8 disponibles, mínimo 10.

## Picking

La oleada `PK-2026-0559` arma el viaje `TMS-088` (Lima → Casma):

| Ubicación | SKU | Pedido | Ya picado |
| --- | --- | --- | --- |
| A-01-01 | Casco | 6 | 2 |
| A-01-02 | Filtro HF-220 | 2 | 0 |
| PICK-01 | Bota 42 | 4 | 0 |

Picar descuenta `en mano` solo si el libre alcanza. Lo reservado no se puede picar. Al completar las líneas, la oleada queda `closed`.

## Transporte

Estados y saltos permitidos:

```
planned → loading → in_transit → delivered
   ↑         ↑          │
   └──── exception ─────┘
```

`TMS-088` está en muelle (`loading`), placa ABC-142. `TMS-091` va a Chimbote. `TMS-076` ya se entregó en Huarmey y no tiene salida. Desde Casma, `TMS-102` está planificado de vuelta a Lima.

Un doble toque con la misma clave de idempotencia no avanza el viaje dos veces.

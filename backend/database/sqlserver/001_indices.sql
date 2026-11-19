-- Índices de producción para SQL Server.
-- La migración de Laravel ya crea los índices básicos. Estos cubren las
-- lecturas calientes del turno (saldo por SKU y viajes del día) y dejan
-- espacio libre en la página porque inventario y reservas se actualizan seguido.
-- FILLFACTOR 80 evita page splits en inventory_balances.

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_balances_wh_sku_cover' AND object_id = OBJECT_ID('dbo.inventory_balances'))
BEGIN
    CREATE INDEX IX_balances_wh_sku_cover
        ON dbo.inventory_balances (warehouse_id, sku_id)
        INCLUDE (location_id, qty_on_hand, qty_reserved, row_version)
        WITH (FILLFACTOR = 80, ONLINE = OFF);
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_shipments_origin_status_cover' AND object_id = OBJECT_ID('dbo.shipments'))
BEGIN
    CREATE INDEX IX_shipments_origin_status_cover
        ON dbo.shipments (origin_warehouse_id, status, planned_at)
        INCLUDE (number, destination_city, plate)
        WITH (FILLFACTOR = 90);
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_scans_wh_time' AND object_id = OBJECT_ID('dbo.scan_events'))
BEGIN
    CREATE INDEX IX_scans_wh_time
        ON dbo.scan_events (warehouse_id, created_at DESC)
        INCLUDE (barcode, context, result);
END;

-- Reserva una fila de saldo con bloqueo de actualización y de fila.
-- UPDLOCK evita que dos terminales lean el mismo disponible y reserven de más.
-- ROWLOCK pide el grano de fila para no bloquear el rack entero.
-- El UPDATE condicional es la garantía: si otra sesión ganó la carrera, no actualiza.

CREATE OR ALTER PROCEDURE dbo.usp_reservar_stock
    @balance_id BIGINT,
    @qty INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF @qty < 1
        THROW 51002, 'La cantidad tiene que ser mayor que cero.', 1;

    BEGIN TRANSACTION;

    DECLARE @on_hand INT;
    DECLARE @reserved INT;

    SELECT
        @on_hand = qty_on_hand,
        @reserved = qty_reserved
    FROM dbo.inventory_balances WITH (UPDLOCK, ROWLOCK)
    WHERE id = @balance_id;

    IF @on_hand IS NULL
    BEGIN
        ROLLBACK TRANSACTION;
        THROW 51000, 'Saldo inexistente.', 1;
    END;

    IF (@on_hand - @reserved) < @qty
    BEGIN
        ROLLBACK TRANSACTION;
        THROW 51001, 'No hay disponible suficiente para reservar.', 1;
    END;

    UPDATE dbo.inventory_balances
    SET qty_reserved = qty_reserved + @qty,
        row_version = row_version + 1,
        updated_at = SYSUTCDATETIME()
    WHERE id = @balance_id
      AND (qty_on_hand - qty_reserved) >= @qty;

    IF @@ROWCOUNT <> 1
    BEGIN
        ROLLBACK TRANSACTION;
        THROW 51001, 'No hay disponible suficiente para reservar.', 1;
    END;

    COMMIT TRANSACTION;
END;

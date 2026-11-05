-- Giro 2 - Campione delle 20 righe più recenti delle tabelle candidate (produzione, eventi, ordini, lotti).
-- Escluse le colonne con nomi di persone. Tutte tabelle piccole (< 30.000 righe).
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
SET LOCK_TIMEOUT 5000;
SET NOCOUNT ON;

DECLARE @tabelle TABLE (n int IDENTITY, nome sysname, ordine nvarchar(200));
INSERT INTO @tabelle (nome, ordine) VALUES
 ('SITUAZIONI', 'DATA DESC'),
 ('EVENTI', 'ORA_EVENTO DESC'),
 ('TASKS', 'DATA_INS DESC'),
 ('ORDINI', 'DATA_INS DESC'),
 ('OPERAZIONI', 'DATA_INS DESC'),
 ('JOBSET', 'TASK DESC'),
 ('FXDECLAREDQTY', 'FDATE DESC'),
 ('LOTTI', 'LOTTO DESC'),
 ('FS_MATERIALS', '(SELECT NULL)'),
 ('MOVIMENTI', 'DATA DESC'),
 ('FXBATCHES', 'FBATCH DESC'),
 ('MATERIALI_ORDINI', '(SELECT NULL)'),
 ('LEGAMI_LOTTI', '(SELECT NULL)');

DECLARE @i int = 1, @max int, @t sysname, @ord nvarchar(200), @cols nvarchar(max), @sql nvarchar(max);
SELECT @max = MAX(n) FROM @tabelle;
WHILE @i <= @max
BEGIN
    SELECT @t = nome, @ord = ordine FROM @tabelle WHERE n = @i;
    SET @cols = STUFF((
        SELECT ', ' + QUOTENAME(c.name)
        FROM sys.columns AS c
        WHERE c.object_id = OBJECT_ID(N'dbo.' + @t)
          AND c.name NOT LIKE '%OPERATOR%' AND c.name NOT LIKE 'OPERATORE%'
          AND c.name NOT IN ('UTENTE', 'UTENTE_UPD', 'FUSER', 'FUPDUSER', 'FLASTPLANUSER', 'RESPONSABILE', 'SQUADRA')
        ORDER BY c.column_id
        FOR XML PATH(''), TYPE).value('.', 'nvarchar(max)'), 1, 2, '');
    IF @cols IS NOT NULL
    BEGIN
        SET @sql = N'SELECT TOP 20 ''' + @t + N''' AS tabella, ' + @cols + N' FROM dbo.' + QUOTENAME(@t)
                 + N' ORDER BY ' + @ord + N';';
        BEGIN TRY
            EXEC sys.sp_executesql @sql;
        END TRY
        BEGIN CATCH
            SELECT @t AS tabella, ERROR_MESSAGE() AS errore;  -- un errore su una tabella non blocca le altre
        END CATCH
    END
    SET @i += 1;
END

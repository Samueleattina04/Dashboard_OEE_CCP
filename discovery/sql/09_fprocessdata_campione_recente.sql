-- FProcessdata: le 200 righe PIÙ RECENTI secondo l'indice cluster/PK (TOP limitato, nessuna scansione completa).
-- Serve a vedere il formato reale dei dati per macchina (FObject) e le colonne valorizzate.
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
SET LOCK_TIMEOUT 5000;

DECLARE @obj int, @tab nvarchar(300), @col sysname, @sql nvarchar(max);
SELECT TOP 1 @obj = object_id, @tab = QUOTENAME(SCHEMA_NAME(schema_id)) + '.' + QUOTENAME(name)
FROM sys.tables WHERE name = N'FProcessdata';

SELECT TOP 1 @col = c.name
FROM sys.indexes AS i
JOIN sys.index_columns AS ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id AND ic.key_ordinal = 1
JOIN sys.columns AS c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
WHERE i.object_id = @obj AND (i.type = 1 OR i.is_primary_key = 1)
ORDER BY i.type;

IF @col IS NULL
    SET @sql = N'SELECT TOP 200 * FROM ' + @tab + N';';  -- senza indice: TOP senza ordinamento, si ferma alle prime righe lette
ELSE
    SET @sql = N'SELECT TOP 200 * FROM ' + @tab + N' ORDER BY ' + QUOTENAME(@col) + N' DESC;';

IF @tab IS NOT NULL EXEC sys.sp_executesql @sql;

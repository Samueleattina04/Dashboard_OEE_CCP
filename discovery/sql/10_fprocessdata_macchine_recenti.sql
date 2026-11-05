-- FProcessdata: quali macchine (FObject) compaiono nelle ultime 20.000 righe e con che frequenza.
-- Limitato alle righe più recenti per non scansionare lo storico.
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
    SELECT 'FProcessdata senza indice cluster/PK: query rimandata al secondo giro' AS nota;
ELSE
BEGIN
    SET @sql = N'WITH r AS (SELECT TOP 20000 FObject, ' + QUOTENAME(@col) + N' AS k FROM ' + @tab
             + N' ORDER BY ' + QUOTENAME(@col) + N' DESC)
SELECT FObject, COUNT(*) AS righe, MIN(k) AS primo_valore_indice, MAX(k) AS ultimo_valore_indice
FROM r GROUP BY FObject ORDER BY righe DESC;';
    EXEC sys.sp_executesql @sql;
END

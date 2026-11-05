-- FProcessdata: primo e ultimo valore della prima colonna dell'indice cluster (o della PK),
-- letti con due TOP 1 sull'indice (seek agli estremi, nessuna scansione).
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;  -- non prende lock condivisi: non blocca le scritture del MES
SET LOCK_TIMEOUT 5000;

DECLARE @obj int, @tab nvarchar(300), @col sysname, @sql nvarchar(max);
SELECT TOP 1 @obj = object_id, @tab = QUOTENAME(SCHEMA_NAME(schema_id)) + '.' + QUOTENAME(name)
FROM sys.tables WHERE name = N'FProcessdata';

SELECT TOP 1 @col = c.name
FROM sys.indexes AS i
JOIN sys.index_columns AS ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id AND ic.key_ordinal = 1
JOIN sys.columns AS c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
WHERE i.object_id = @obj AND (i.type = 1 OR i.is_primary_key = 1)
ORDER BY i.type;  -- prima il cluster

IF @obj IS NULL
    SELECT 'Tabella FProcessdata non trovata' AS nota;
ELSE IF @col IS NULL
    SELECT 'FProcessdata non ha indice cluster/PK: estremi non calcolati per non scansionare la tabella' AS nota;
ELSE
BEGIN
    SET @sql = N'SELECT ''' + @col + N''' AS colonna_indice,
        (SELECT TOP 1 ' + QUOTENAME(@col) + N' FROM ' + @tab + N' ORDER BY ' + QUOTENAME(@col) + N' ASC)  AS primo_valore,
        (SELECT TOP 1 ' + QUOTENAME(@col) + N' FROM ' + @tab + N' ORDER BY ' + QUOTENAME(@col) + N' DESC) AS ultimo_valore;';
    EXEC sys.sp_executesql @sql;
END

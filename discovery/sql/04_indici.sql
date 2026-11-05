-- Indici (inclusi PK/cluster) con colonne chiave e incluse: servono per scrivere query che non caricano il MES.
SELECT
    s.name          AS schema_nome,
    t.name          AS tabella,
    i.name          AS indice,
    i.type_desc     AS tipo_indice,
    i.is_primary_key,
    i.is_unique,
    STUFF((
        SELECT ', ' + c.name + CASE WHEN ic.is_descending_key = 1 THEN ' DESC' ELSE '' END
        FROM sys.index_columns AS ic
        JOIN sys.columns AS c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
        WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id AND ic.is_included_column = 0
        ORDER BY ic.key_ordinal
        FOR XML PATH(''), TYPE).value('.', 'nvarchar(max)'), 1, 2, '') AS colonne_chiave,
    STUFF((
        SELECT ', ' + c.name
        FROM sys.index_columns AS ic
        JOIN sys.columns AS c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
        WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id AND ic.is_included_column = 1
        ORDER BY ic.index_column_id
        FOR XML PATH(''), TYPE).value('.', 'nvarchar(max)'), 1, 2, '') AS colonne_incluse
FROM sys.indexes AS i
JOIN sys.tables  AS t ON t.object_id = i.object_id AND t.is_ms_shipped = 0
JOIN sys.schemas AS s ON s.schema_id = t.schema_id
WHERE i.type > 0
ORDER BY s.name, t.name, i.index_id;

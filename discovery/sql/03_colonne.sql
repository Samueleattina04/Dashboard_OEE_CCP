-- Colonne di tutte le tabelle/viste: tipo, nullabilità, identity, descrizione (se presente).
SELECT
    s.name          AS schema_nome,
    o.name          AS oggetto,
    o.type_desc     AS tipo_oggetto,
    c.column_id     AS posizione,
    c.name          AS colonna,
    ty.name         AS tipo_dato,
    c.max_length,
    c.precision,
    c.scale,
    c.is_nullable,
    c.is_identity,
    c.is_computed,
    CAST(ep.value AS nvarchar(4000)) AS descrizione
FROM sys.columns AS c
JOIN sys.objects AS o ON o.object_id = c.object_id AND o.type IN ('U', 'V') AND o.is_ms_shipped = 0
JOIN sys.schemas AS s ON s.schema_id = o.schema_id
JOIN sys.types   AS ty ON ty.user_type_id = c.user_type_id
LEFT JOIN sys.extended_properties AS ep
       ON ep.class = 1 AND ep.major_id = c.object_id AND ep.minor_id = c.column_id AND ep.name = 'MS_Description'
ORDER BY s.name, o.name, c.column_id;

-- Elenco tabelle e viste con numero righe STIMATO dai metadati (sys.partitions):
-- nessuna scansione dei dati.
SELECT
    s.name          AS schema_nome,
    o.name          AS oggetto,
    o.type_desc     AS tipo,
    o.create_date   AS creata_il,
    o.modify_date   AS modificata_il,
    SUM(CASE WHEN p.index_id IN (0, 1) THEN p.rows END) AS righe_stimate
FROM sys.objects AS o
JOIN sys.schemas AS s ON s.schema_id = o.schema_id
LEFT JOIN sys.partitions AS p ON p.object_id = o.object_id
WHERE o.type IN ('U', 'V') AND o.is_ms_shipped = 0
GROUP BY s.name, o.name, o.type_desc, o.create_date, o.modify_date
ORDER BY righe_stimate DESC, s.name, o.name;

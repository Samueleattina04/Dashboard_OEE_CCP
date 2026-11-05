-- Definizioni di viste, procedure, funzioni e trigger (solo lettura del testo, nulla viene eseguito).
-- Le viste/procedure dei report nativi del MES spiegano come il fornitore calcola i dati.
-- Se l'utente non ha VIEW DEFINITION la colonna "definizione" sarà vuota.
SELECT
    s.name          AS schema_nome,
    o.name          AS oggetto,
    o.type_desc     AS tipo,
    o.modify_date   AS modificato_il,
    OBJECT_DEFINITION(o.object_id) AS definizione
FROM sys.objects AS o
JOIN sys.schemas AS s ON s.schema_id = o.schema_id
WHERE o.type IN ('V', 'P', 'FN', 'IF', 'TF', 'TR') AND o.is_ms_shipped = 0
ORDER BY o.type_desc, s.name, o.name;

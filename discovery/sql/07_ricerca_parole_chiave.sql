-- Tabelle e colonne il cui nome contiene parole chiave OEE / CCP (solo metadati).
-- È normale che ci sia "rumore": serve a non perdere tabelle candidate.
WITH parole (parola, area) AS (
    SELECT * FROM (VALUES
        ('ccp','CCP'),('haccp','CCP'),('temp','CCP'),('limit','CCP'),('soglia','CCP'),('allarm','CCP'),
        ('alarm','CCP'),('metal','CCP'),('setac','CCP'),('sieve','CCP'),('peso','CCP'),('weight','CCP'),
        ('esito','CCP'),('conform','CCP'),('deviaz','CCP'),('azion','CCP'),('firma','CCP'),('control','CCP'),
        ('stato','EVENTI'),('state','EVENTI'),('status','EVENTI'),('fermo','EVENTI'),('stop','EVENTI'),
        ('causal','EVENTI'),('reason','EVENTI'),('event','EVENTI'),('guast','EVENTI'),('setup','EVENTI'),
        ('marcia','EVENTI'),('run','EVENTI'),('downtime','EVENTI'),
        ('pezz','PRODUZIONE'),('count','PRODUZIONE'),('qty','PRODUZIONE'),('quant','PRODUZIONE'),
        ('scart','PRODUZIONE'),('scrap','PRODUZIONE'),('reject','PRODUZIONE'),('kg','PRODUZIONE'),
        ('velocit','PRODUZIONE'),('speed','PRODUZIONE'),('cicl','PRODUZIONE'),('cycle','PRODUZIONE'),
        ('ordin','PRODUZIONE'),('order','PRODUZIONE'),('lott','PRODUZIONE'),('batch','PRODUZIONE'),
        ('process','PRODUZIONE'),
        ('macchin','ANAGRAFICA'),('machine','ANAGRAFICA'),('object','ANAGRAFICA'),('impiant','ANAGRAFICA'),
        ('linea','ANAGRAFICA'),('repart','ANAGRAFICA'),('articol','ANAGRAFICA'),('item','ANAGRAFICA'),
        ('prodott','ANAGRAFICA'),('product','ANAGRAFICA'),('turn','ANAGRAFICA'),('shift','ANAGRAFICA'),
        ('operat','ANAGRAFICA'),('device','ANAGRAFICA'),('tag','ANAGRAFICA'),('variab','ANAGRAFICA')
    ) AS v (parola, area)
)
SELECT DISTINCT
    p.area,
    p.parola,
    s.name AS schema_nome,
    o.name AS oggetto,
    o.type_desc AS tipo_oggetto,
    CASE WHEN o.name LIKE '%' + p.parola + '%' THEN '(nome tabella)' ELSE c.name END AS colonna
FROM sys.objects AS o
JOIN sys.schemas AS s ON s.schema_id = o.schema_id
JOIN sys.columns AS c ON c.object_id = o.object_id
JOIN parole AS p ON o.name LIKE '%' + p.parola + '%' OR c.name LIKE '%' + p.parola + '%'
WHERE o.type IN ('U', 'V') AND o.is_ms_shipped = 0
ORDER BY p.area, s.name, o.name, colonna;

-- Giro 5 - BROVRAF (raffinatrice Brovind linea pasta): c'è uno storico utilizzabile?
-- Per mese: righe, righe con almeno un valore diverso da zero, min/max delle temperature prodotto,
-- righe con velocità motore > 0. Lettura su indice cluster (FOBJECT, FOBJTYPE, FCHANNEL, FTIMESTAMP).
-- Risultato 2: valori distinti di FVALUE11 (stato PLC) con conteggio.
SET LOCK_TIMEOUT 5000;
SET NOCOUNT ON;

SELECT CONVERT(char(7), FTIMESTAMP, 120) AS mese,
       COUNT(*) AS righe,
       SUM(CASE WHEN COALESCE(TRY_CONVERT(decimal(15,5), REPLACE(FVALUE01, ',', '.')), 0) <> 0
                  OR COALESCE(TRY_CONVERT(decimal(15,5), REPLACE(FVALUE02, ',', '.')), 0) <> 0
                  OR COALESCE(TRY_CONVERT(decimal(15,5), REPLACE(FVALUE05, ',', '.')), 0) <> 0
                  OR COALESCE(TRY_CONVERT(decimal(15,5), REPLACE(FVALUE06, ',', '.')), 0) <> 0
                  OR COALESCE(TRY_CONVERT(decimal(15,5), REPLACE(FVALUE07, ',', '.')), 0) <> 0
                  OR COALESCE(TRY_CONVERT(decimal(15,5), REPLACE(FVALUE08, ',', '.')), 0) <> 0
                 THEN 1 ELSE 0 END) AS righe_con_valori,
       SUM(CASE WHEN COALESCE(TRY_CONVERT(decimal(15,5), REPLACE(FVALUE07, ',', '.')), 0) > 0
                  OR COALESCE(TRY_CONVERT(decimal(15,5), REPLACE(FVALUE08, ',', '.')), 0) > 0
                 THEN 1 ELSE 0 END) AS righe_motore_acceso,
       MIN(TRY_CONVERT(decimal(15,5), REPLACE(FVALUE01, ',', '.'))) AS min_temp_prod1,
       MAX(TRY_CONVERT(decimal(15,5), REPLACE(FVALUE01, ',', '.'))) AS max_temp_prod1,
       MAX(TRY_CONVERT(decimal(15,5), REPLACE(FVALUE02, ',', '.'))) AS max_temp_prod2,
       SUM(CASE WHEN FVALUE01 IS NULL OR LTRIM(RTRIM(FVALUE01)) = N'' THEN 1 ELSE 0 END) AS righe_vuote
FROM dbo.FPROCESSDATA
WHERE FOBJECT = N'BROVRAF' AND FOBJTYPE = N'MAC' AND FCHANNEL = N'HIST'
GROUP BY CONVERT(char(7), FTIMESTAMP, 120)
ORDER BY mese;

SELECT ISNULL(NULLIF(LTRIM(RTRIM(FVALUE11)), N''), N'(vuoto)') AS stato_plc, COUNT(*) AS righe,
       MIN(FTIMESTAMP) AS primo, MAX(FTIMESTAMP) AS ultimo
FROM dbo.FPROCESSDATA
WHERE FOBJECT = N'BROVRAF' AND FOBJTYPE = N'MAC' AND FCHANNEL = N'HIST'
GROUP BY ISNULL(NULLIF(LTRIM(RTRIM(FVALUE11)), N''), N'(vuoto)')
ORDER BY righe DESC;

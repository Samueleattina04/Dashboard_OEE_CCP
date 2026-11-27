-- Giro 10 - Tutti i valori di FVALUE01 nello storico HIST di FPROCESSDATA, per macchina.
-- ATTENZIONE: legge tutto lo storico (circa 2,5 milioni di righe). Lanciarlo fuori dall'orario di punta.
-- READ UNCOMMITTED: la lettura completa non prende lock che rallentino le scritture di FDI.
-- 1) Valori di testo (es. stati delle selezionatrici): elenco completo con conteggio e periodo.
-- 2) Valori numerici (temperature, portate): riepilogo per macchina.
-- 3) Campioni vuoti per macchina.
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
SET NOCOUNT ON;

SELECT FOBJECT AS macchina, LTRIM(RTRIM(FVALUE01)) AS valore, COUNT(*) AS campioni,
       CONVERT(varchar(19), MIN(FTIMESTAMP), 120) AS primo, CONVERT(varchar(19), MAX(FTIMESTAMP), 120) AS ultimo
FROM dbo.FPROCESSDATA
WHERE FOBJTYPE = N'MAC' AND FCHANNEL = N'HIST'
  AND LTRIM(RTRIM(ISNULL(FVALUE01, N''))) <> N''
  AND TRY_CONVERT(decimal(18, 5), REPLACE(FVALUE01, ',', '.')) IS NULL
GROUP BY FOBJECT, LTRIM(RTRIM(FVALUE01))
ORDER BY FOBJECT, campioni DESC;

SELECT FOBJECT AS macchina, COUNT(*) AS campioni_numerici,
       COUNT(DISTINCT FVALUE01) AS valori_distinti,
       MIN(TRY_CONVERT(decimal(18, 5), REPLACE(FVALUE01, ',', '.'))) AS minimo,
       MAX(TRY_CONVERT(decimal(18, 5), REPLACE(FVALUE01, ',', '.'))) AS massimo,
       SUM(CASE WHEN TRY_CONVERT(decimal(18, 5), REPLACE(FVALUE01, ',', '.')) = 0 THEN 1 ELSE 0 END) AS campioni_a_zero,
       CONVERT(varchar(19), MIN(FTIMESTAMP), 120) AS primo, CONVERT(varchar(19), MAX(FTIMESTAMP), 120) AS ultimo
FROM dbo.FPROCESSDATA
WHERE FOBJTYPE = N'MAC' AND FCHANNEL = N'HIST'
  AND TRY_CONVERT(decimal(18, 5), REPLACE(FVALUE01, ',', '.')) IS NOT NULL
GROUP BY FOBJECT
ORDER BY FOBJECT;

SELECT FOBJECT AS macchina, COUNT(*) AS campioni_vuoti,
       CONVERT(varchar(19), MIN(FTIMESTAMP), 120) AS primo, CONVERT(varchar(19), MAX(FTIMESTAMP), 120) AS ultimo
FROM dbo.FPROCESSDATA
WHERE FOBJTYPE = N'MAC' AND FCHANNEL = N'HIST' AND LTRIM(RTRIM(ISNULL(FVALUE01, N''))) = N''
GROUP BY FOBJECT
ORDER BY FOBJECT;

-- Giro 6 - SITUAZIONI di ULMA e PELAB: riepilogo per macchina, mese, tipo oggetto e tipo di situazione.
-- Tabella piccola (~25.000 righe in tutto): lettura completa accettabile.
SET LOCK_TIMEOUT 5000;
SET NOCOUNT ON;

SELECT MACCHINA, CONVERT(char(7), DATA, 120) AS mese, TIPO_OGGETTO, TIPO,
       COUNT(*) AS righe,
       COUNT(DISTINCT CAST(DATA AS date)) AS giorni,
       SUM(LAVORO) AS ore_lavoro, SUM(SETUP) AS ore_setup,
       SUM(QTA_CONTATA) AS qta_contata, SUM(QTA_COMPLETATA) AS qta_completata, SUM(QTA_SCARTATA) AS qta_scartata,
       MIN(ORA_INIZIO) AS prima_ora_inizio, MAX(ORA_FINE) AS ultima_ora_fine,
       COUNT(DISTINCT TASK) AS task_distinti, MIN(TASK) AS task_esempio, MIN(PART_NUMBER) AS articolo_esempio
FROM dbo.SITUAZIONI
WHERE MACCHINA IN (N'ULMA', N'PELAB')
GROUP BY MACCHINA, CONVERT(char(7), DATA, 120), TIPO_OGGETTO, TIPO
ORDER BY MACCHINA, mese, TIPO_OGGETTO, TIPO;

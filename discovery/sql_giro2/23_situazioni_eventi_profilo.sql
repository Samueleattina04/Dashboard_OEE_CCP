-- Giro 2 - Profilo di SITUAZIONI (~25.000 righe) ed EVENTI (~1.400 righe):
-- quali macchine, tipi, causali, turni compaiono, con periodo coperto. Tabelle piccole: aggregazione completa.
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
SET LOCK_TIMEOUT 5000;
SET NOCOUNT ON;

SELECT 'SITUAZIONI' AS tabella, OGGETTO, TIPO_OGGETTO, MACCHINA, TIPO, CAUSALE, TURNO,
       COUNT(*) AS righe, MIN(DATA) AS prima_data, MAX(DATA) AS ultima_data,
       MIN(ORA_INIZIO) AS min_ora_inizio, MAX(ORA_FINE) AS max_ora_fine,
       SUM(LAVORO) AS somma_lavoro, SUM(SETUP) AS somma_setup,
       SUM(QTA_CONTATA) AS somma_qta_contata, SUM(QTA_SCARTATA) AS somma_qta_scartata
FROM dbo.SITUAZIONI
GROUP BY OGGETTO, TIPO_OGGETTO, MACCHINA, TIPO, CAUSALE, TURNO
ORDER BY MACCHINA, TIPO, CAUSALE;

SELECT 'EVENTI' AS tabella, MACCHINA, TIPO_OGGETTO, EVENTO, CAUSALE, SOURCE,
       COUNT(*) AS righe, MIN(ORA_EVENTO) AS primo_evento, MAX(ORA_EVENTO) AS ultimo_evento,
       SUM(TEMPO) AS somma_tempo, SUM(TEMPO_SETUP) AS somma_tempo_setup
FROM dbo.EVENTI
GROUP BY MACCHINA, TIPO_OGGETTO, EVENTO, CAUSALE, SOURCE
ORDER BY MACCHINA, EVENTO, CAUSALE;

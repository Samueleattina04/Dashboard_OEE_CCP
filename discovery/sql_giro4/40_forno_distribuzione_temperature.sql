-- Giro 4 - Forno FORNOKING500: distribuzione della temperatura di tostatura (FVALUE02)
-- negli ultimi 12 mesi, per scegliere la soglia "forno in funzione".
-- Lettura su indice cluster (FOBJECT, FOBJTYPE, FCHANNEL, FTIMESTAMP): ~35.000 righe.
-- Risultato 1: istogramma complessivo a fasce di 10 °C.
-- Risultato 2: per giorno, campioni per fascia (righe = giorno x fascia).
-- Risultato 3: campioni con valore vuoto o non numerico, per mese.
SET LOCK_TIMEOUT 5000;
SET NOCOUNT ON;

DECLARE @da datetime = DATEADD(MONTH, -12, CAST(GETDATE() AS date));

SELECT FLOOR(TRY_CONVERT(decimal(9,2), REPLACE(FVALUE02, ',', '.')) / 10) * 10 AS fascia_10C,
       COUNT(*) AS campioni
FROM dbo.FPROCESSDATA
WHERE FOBJECT = N'FORNOKING500' AND FOBJTYPE = N'MAC' AND FCHANNEL = N'HIST' AND FTIMESTAMP >= @da
GROUP BY FLOOR(TRY_CONVERT(decimal(9,2), REPLACE(FVALUE02, ',', '.')) / 10) * 10
ORDER BY fascia_10C;

SELECT CAST(FTIMESTAMP AS date) AS giorno,
       FLOOR(TRY_CONVERT(decimal(9,2), REPLACE(FVALUE02, ',', '.')) / 10) * 10 AS fascia_10C,
       COUNT(*) AS campioni,
       MIN(TRY_CONVERT(decimal(9,2), REPLACE(FVALUE02, ',', '.'))) AS min_C,
       MAX(TRY_CONVERT(decimal(9,2), REPLACE(FVALUE02, ',', '.'))) AS max_C
FROM dbo.FPROCESSDATA
WHERE FOBJECT = N'FORNOKING500' AND FOBJTYPE = N'MAC' AND FCHANNEL = N'HIST' AND FTIMESTAMP >= @da
GROUP BY CAST(FTIMESTAMP AS date), FLOOR(TRY_CONVERT(decimal(9,2), REPLACE(FVALUE02, ',', '.')) / 10) * 10
ORDER BY giorno, fascia_10C;

SELECT CONVERT(char(7), FTIMESTAMP, 120) AS mese,
       COUNT(*) AS campioni_totali,
       SUM(CASE WHEN FVALUE02 IS NULL OR LTRIM(RTRIM(FVALUE02)) = N'' THEN 1 ELSE 0 END) AS vuoti,
       SUM(CASE WHEN FVALUE02 IS NOT NULL AND LTRIM(RTRIM(FVALUE02)) <> N''
                 AND TRY_CONVERT(decimal(9,2), REPLACE(FVALUE02, ',', '.')) IS NULL THEN 1 ELSE 0 END) AS non_numerici
FROM dbo.FPROCESSDATA
WHERE FOBJECT = N'FORNOKING500' AND FOBJTYPE = N'MAC' AND FCHANNEL = N'HIST' AND FTIMESTAMP >= @da
GROUP BY CONVERT(char(7), FTIMESTAMP, 120)
ORDER BY mese;

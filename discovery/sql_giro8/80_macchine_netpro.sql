-- Giro 8 - Quali macchine ci sono in NetPro e con quali dati (per individuare macchine nuove).
-- 1) Anagrafica MACCHINE (tutte, ~26 righe; esclusa la colonna RESPONSABILE).
-- 2) Segnali in tempo reale (FIOLINES) raggruppati per macchina, con l'ultima lettura dal PLC.
-- 3) Storico FPROCESSDATA: combinazioni FOBJECT/FOBJTYPE/FCHANNEL ricavate con "skip scan" sulla PK
--    (una lettura puntuale per combinazione), con primo/ultimo FTIMESTAMP e righe degli ultimi 7 giorni.
SET LOCK_TIMEOUT 5000;
SET NOCOUNT ON;

SELECT MACCHINA, DESCRIZIONE, CENTRO, TIPO, TIPO_RILEVAZIONE, STATO_FISICO,
       CONVERT(varchar(19), ORA_ST_FISICO, 120) AS ora_st_fisico, STATO_LOGICO, TASK, INTERFACCIA
FROM dbo.MACCHINE
ORDER BY MACCHINA;

SELECT FOBJECT, COUNT(*) AS segnali,
       CONVERT(varchar(19), MAX(FREADDATE), 120) AS ultima_lettura_plc,
       STUFF((SELECT ', ' + f2.FIOLINE + ' (' + ISNULL(f2.FDESCR, '') + ')'
              FROM dbo.FIOLINES AS f2 WHERE f2.FOBJECT = f.FOBJECT ORDER BY f2.FIOLINE
              FOR XML PATH(''), TYPE).value('.', 'nvarchar(max)'), 1, 2, '') AS elenco_segnali
FROM dbo.FIOLINES AS f
GROUP BY FOBJECT
ORDER BY FOBJECT;

DECLARE @combo TABLE (FOBJECT nvarchar(40), FOBJTYPE nvarchar(40), FCHANNEL nvarchar(40));
DECLARE @o nvarchar(40), @ty nvarchar(40), @ch nvarchar(40), @giri int = 0;
DECLARE @no nvarchar(40), @nty nvarchar(40), @nch nvarchar(40);

SELECT TOP 1 @o = FOBJECT, @ty = FOBJTYPE, @ch = FCHANNEL FROM dbo.FPROCESSDATA ORDER BY FOBJECT, FOBJTYPE, FCHANNEL;
WHILE @o IS NOT NULL AND @giri < 500
BEGIN
    INSERT INTO @combo VALUES (@o, @ty, @ch);
    SET @giri += 1;
    SELECT @no = NULL, @nty = NULL, @nch = NULL;
    SELECT TOP 1 @no = FOBJECT, @nty = FOBJTYPE, @nch = FCHANNEL FROM dbo.FPROCESSDATA
    WHERE FOBJECT = @o AND FOBJTYPE = @ty AND FCHANNEL > @ch ORDER BY FCHANNEL;
    IF @no IS NULL
        SELECT TOP 1 @no = FOBJECT, @nty = FOBJTYPE, @nch = FCHANNEL FROM dbo.FPROCESSDATA
        WHERE FOBJECT = @o AND FOBJTYPE > @ty ORDER BY FOBJTYPE, FCHANNEL;
    IF @no IS NULL
        SELECT TOP 1 @no = FOBJECT, @nty = FOBJTYPE, @nch = FCHANNEL FROM dbo.FPROCESSDATA
        WHERE FOBJECT > @o ORDER BY FOBJECT, FOBJTYPE, FCHANNEL;
    SELECT @o = @no, @ty = @nty, @ch = @nch;
END

DECLARE @da7 datetime = DATEADD(DAY, -7, GETDATE());
SELECT c.FOBJECT, c.FOBJTYPE, c.FCHANNEL,
       (SELECT TOP 1 CONVERT(varchar(19), p.FTIMESTAMP, 120) FROM dbo.FPROCESSDATA AS p
         WHERE p.FOBJECT = c.FOBJECT AND p.FOBJTYPE = c.FOBJTYPE AND p.FCHANNEL = c.FCHANNEL ORDER BY p.FTIMESTAMP ASC) AS primo_timestamp,
       (SELECT TOP 1 CONVERT(varchar(19), p.FTIMESTAMP, 120) FROM dbo.FPROCESSDATA AS p
         WHERE p.FOBJECT = c.FOBJECT AND p.FOBJTYPE = c.FOBJTYPE AND p.FCHANNEL = c.FCHANNEL ORDER BY p.FTIMESTAMP DESC) AS ultimo_timestamp,
       (SELECT COUNT(*) FROM dbo.FPROCESSDATA AS p
         WHERE p.FOBJECT = c.FOBJECT AND p.FOBJTYPE = c.FOBJTYPE AND p.FCHANNEL = c.FCHANNEL AND p.FTIMESTAMP >= @da7) AS righe_ultimi_7_giorni
FROM @combo AS c
ORDER BY c.FOBJECT, c.FOBJTYPE, c.FCHANNEL;

-- Giro 2 - FPROCESSDATA (~2,1 milioni di righe), senza scansioni:
-- 1) elenco delle combinazioni FOBJECT / FOBJTYPE / FCHANNEL ricavate con "skip scan" sulla PK
--    (una lettura puntuale per combinazione), con primo/ultimo FTIMESTAMP e righe degli ultimi 7 giorni;
-- 2) le 20 righe più recenti per ogni combinazione (colonna FOPERATOR esclusa).
-- Le tabelle variabili stanno in tempdb: nulla viene scritto nel database NETPRO.
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
SET LOCK_TIMEOUT 5000;
SET NOCOUNT ON;

DECLARE @combo TABLE (FOBJECT nvarchar(40), FOBJTYPE nvarchar(40), FCHANNEL nvarchar(40));
DECLARE @o nvarchar(40), @ty nvarchar(40), @ch nvarchar(40), @giri int = 0;
DECLARE @no nvarchar(40), @nty nvarchar(40), @nch nvarchar(40);

SELECT TOP 1 @o = FOBJECT, @ty = FOBJTYPE, @ch = FCHANNEL
FROM dbo.FPROCESSDATA ORDER BY FOBJECT, FOBJTYPE, FCHANNEL;

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
       (SELECT TOP 1 p.FTIMESTAMP FROM dbo.FPROCESSDATA AS p
         WHERE p.FOBJECT = c.FOBJECT AND p.FOBJTYPE = c.FOBJTYPE AND p.FCHANNEL = c.FCHANNEL
         ORDER BY p.FTIMESTAMP ASC)  AS primo_timestamp,
       (SELECT TOP 1 p.FTIMESTAMP FROM dbo.FPROCESSDATA AS p
         WHERE p.FOBJECT = c.FOBJECT AND p.FOBJTYPE = c.FOBJTYPE AND p.FCHANNEL = c.FCHANNEL
         ORDER BY p.FTIMESTAMP DESC) AS ultimo_timestamp,
       (SELECT COUNT(*) FROM dbo.FPROCESSDATA AS p
         WHERE p.FOBJECT = c.FOBJECT AND p.FOBJTYPE = c.FOBJTYPE AND p.FCHANNEL = c.FCHANNEL
           AND p.FTIMESTAMP >= @da7) AS righe_ultimi_7_giorni
FROM @combo AS c
ORDER BY c.FOBJECT, c.FOBJTYPE, c.FCHANNEL;

DECLARE @cols nvarchar(max) = STUFF((
    SELECT ', r.' + QUOTENAME(c.name)
    FROM sys.columns AS c
    WHERE c.object_id = OBJECT_ID(N'dbo.FPROCESSDATA') AND c.name <> 'FOPERATOR'
    ORDER BY c.column_id
    FOR XML PATH(''), TYPE).value('.', 'nvarchar(max)'), 1, 2, '');

DECLARE @sql nvarchar(max) = N'
SELECT ' + @cols + N'
FROM @combo AS c
CROSS APPLY (SELECT TOP 20 p.* FROM dbo.FPROCESSDATA AS p
             WHERE p.FOBJECT = c.FOBJECT AND p.FOBJTYPE = c.FOBJTYPE AND p.FCHANNEL = c.FCHANNEL
             ORDER BY p.FTIMESTAMP DESC) AS r
ORDER BY r.FOBJECT, r.FOBJTYPE, r.FCHANNEL, r.FTIMESTAMP DESC;';
-- @combo non è visibile dentro sp_executesql: si passa tramite una tabella temporanea
SELECT * INTO #combo FROM @combo;
SET @sql = REPLACE(@sql, N'@combo', N'#combo');
EXEC sys.sp_executesql @sql;

-- Giro 2 - Contenuto delle tabelle anagrafiche/configurazione (tutte < 300 righe).
-- Escluse le colonne con nomi di persone (operatori, utenti, responsabili).
-- Un risultato per tabella; la prima colonna "tabella" dice da dove viene.
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
SET LOCK_TIMEOUT 5000;
SET NOCOUNT ON;

DECLARE @tabelle TABLE (n int IDENTITY, nome sysname);
INSERT INTO @tabelle (nome) VALUES
 ('MACCHINE'),('TURNI'),('TURNI_MENSA'),('REPARTI'),('AREE'),('CENTRI'),('CENTRI_COSTO'),('AZIENDE'),
 ('VALORI_TABELLE'),('PROFILI_OGGETTI'),('FRTSIGNALS'),('FIOLINES'),('FTASKIOLINES'),('ATTIVITA'),
 ('POSTAZIONI'),('PROFILI_POSTAZIONI'),('CICLI_STD'),('OPERAZIONI_STD'),('ANAG_MATERIALI'),
 ('FCHARTXR_DEFAULTS'),('FAQLRANGE'),('FINSPECTIONLOTSIZES'),('FCOSTS_CONFIG'),('FCOSTS_CONFIG_ANOMALIES'),
 ('FMODSTATISTICS'),('FS_ROUTINGS'),('DISTINTE_BASE');

DECLARE @i int = 1, @max int, @t sysname, @cols nvarchar(max), @sql nvarchar(max);
SELECT @max = MAX(n) FROM @tabelle;
WHILE @i <= @max
BEGIN
    SELECT @t = nome FROM @tabelle WHERE n = @i;
    SET @cols = STUFF((
        SELECT ', ' + QUOTENAME(c.name)
        FROM sys.columns AS c
        WHERE c.object_id = OBJECT_ID(N'dbo.' + @t)
          AND c.name NOT LIKE '%OPERATOR%' AND c.name NOT LIKE 'OPERATORE%'
          AND c.name NOT IN ('UTENTE', 'UTENTE_UPD', 'FUSER', 'FUPDUSER', 'FLASTPLANUSER', 'RESPONSABILE', 'SQUADRA')
        ORDER BY c.column_id
        FOR XML PATH(''), TYPE).value('.', 'nvarchar(max)'), 1, 2, '');
    IF @cols IS NOT NULL
    BEGIN
        SET @sql = N'SELECT TOP 500 ''' + @t + N''' AS tabella, ' + @cols + N' FROM dbo.' + QUOTENAME(@t) + N';';
        BEGIN TRY
            EXEC sys.sp_executesql @sql;
        END TRY
        BEGIN CATCH
            SELECT @t AS tabella, ERROR_MESSAGE() AS errore;  -- un errore su una tabella non blocca le altre
        END CATCH
    END
    SET @i += 1;
END

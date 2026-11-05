-- Giro 3 - Campioni: 30 righe per tabella, ordinate per StorageTime decrescente (testo)
-- e 30 per IDrow decrescente, per capire il formato e la frequenza di registrazione.
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
SET LOCK_TIMEOUT 5000;
SET NOCOUNT ON;

SELECT TOP 30 'K800F_IMP per StorageTime' AS ordinamento, * FROM dbo.K800F_IMP ORDER BY StorageTime DESC;
SELECT TOP 30 'K800F_IMP per IDrow' AS ordinamento, * FROM dbo.K800F_IMP ORDER BY IDrow DESC;
SELECT TOP 30 'K800P_IMP per StorageTime' AS ordinamento, * FROM dbo.K800P_IMP ORDER BY StorageTime DESC;
SELECT TOP 13 'FMT_IMP' AS ordinamento, * FROM dbo.FMT_IMP;

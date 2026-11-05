-- Giro 3 - Database MACCHINE (eseguire con -Database MACCHINE).
-- Tabelle *_IMP importate da AppDBI (forno/raffinatrice Brovind, FMT, silos).
-- Tutte piccole (< 100.000 righe): conteggio ed estremi con lettura completa accettabile.
-- Le colonne sono tutte nvarchar: si mostrano MIN/MAX testuali, senza conversioni.
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
SET LOCK_TIMEOUT 5000;
SET NOCOUNT ON;

SELECT 'K800F_IMP' AS tabella, COUNT(*) AS righe,
       MIN(StorageTime) AS min_storagetime, MAX(StorageTime) AS max_storagetime,
       MIN(IDrow) AS min_idrow, MAX(IDrow) AS max_idrow,
       COUNT(DISTINCT LOTTO) AS lotti_distinti, COUNT(DISTINCT RecipeROASTER) AS ricette_distinte
FROM dbo.K800F_IMP
UNION ALL
SELECT 'K800P_IMP', COUNT(*), MIN(StorageTime), MAX(StorageTime), MIN(IDrow), MAX(IDrow),
       COUNT(DISTINCT LOTRefiner1), COUNT(DISTINCT RecipeREFINING)
FROM dbo.K800P_IMP;

-- Giro 3 - Distribuzioni utili per i CCP del forno:
-- 1) valori distinti di status e ricette (con conteggio);
-- 2) righe per "giorno" (primi 10 caratteri di StorageTime) per capire periodo e frequenza.
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
SET LOCK_TIMEOUT 5000;
SET NOCOUNT ON;

SELECT 'status' AS campo, status AS valore, COUNT(*) AS righe FROM dbo.K800F_IMP GROUP BY status
UNION ALL
SELECT 'RecipeROASTER', RecipeROASTER, COUNT(*) FROM dbo.K800F_IMP GROUP BY RecipeROASTER
UNION ALL
SELECT 'RecipeCOOLER', RecipeCOOLER, COUNT(*) FROM dbo.K800F_IMP GROUP BY RecipeCOOLER
ORDER BY campo, righe DESC;

SELECT LEFT(StorageTime, 10) AS giorno, COUNT(*) AS righe,
       MIN(StorageTime) AS prima, MAX(StorageTime) AS ultima, COUNT(DISTINCT LOTTO) AS lotti
FROM dbo.K800F_IMP
GROUP BY LEFT(StorageTime, 10)
ORDER BY giorno DESC;

SELECT 'SILOS8CR_IMP' AS tabella, COUNT(*) AS righe, MIN(DATA8) AS min_data, MAX(DATA8) AS max_data FROM dbo.SILOS8CR_IMP
UNION ALL SELECT 'SILOS8PP_IMP', COUNT(*), MIN(DATA8), MAX(DATA8) FROM dbo.SILOS8PP_IMP;

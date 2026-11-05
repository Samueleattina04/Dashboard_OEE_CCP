-- Permessi effettivi e ruoli dell'utente connesso (evidenza "sola lettura" per la Fase 4).
SELECT 'permesso_database' AS tipo, permission_name AS valore
FROM fn_my_permissions(NULL, 'DATABASE')
UNION ALL
SELECT 'ruolo_database', r.name
FROM sys.database_role_members AS m
JOIN sys.database_principals AS r ON r.principal_id = m.role_principal_id
WHERE m.member_principal_id = DATABASE_PRINCIPAL_ID()
ORDER BY tipo, valore;

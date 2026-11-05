-- Relazioni dichiarate (foreign key). Spesso nei MES sono poche o assenti: in quel caso le relazioni
-- andranno dedotte dai nomi colonna e CONFERMATE da voi (nessun mapping inventato).
SELECT
    fk.name                                   AS chiave_esterna,
    SCHEMA_NAME(tp.schema_id) + '.' + tp.name AS tabella_figlia,
    cp.name                                   AS colonna_figlia,
    SCHEMA_NAME(tr.schema_id) + '.' + tr.name AS tabella_padre,
    cr.name                                   AS colonna_padre
FROM sys.foreign_keys AS fk
JOIN sys.foreign_key_columns AS fkc ON fkc.constraint_object_id = fk.object_id
JOIN sys.tables  AS tp ON tp.object_id = fkc.parent_object_id
JOIN sys.columns AS cp ON cp.object_id = fkc.parent_object_id     AND cp.column_id = fkc.parent_column_id
JOIN sys.tables  AS tr ON tr.object_id = fkc.referenced_object_id
JOIN sys.columns AS cr ON cr.object_id = fkc.referenced_object_id AND cr.column_id = fkc.referenced_column_id
ORDER BY tabella_figlia, chiave_esterna;

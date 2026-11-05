-- Fase 1 - Scoperta DB (SOLA LETTURA)
-- Versione SQL Server, edizione, database, utente connesso, ora/fuso del server.
SELECT
    @@VERSION                                  AS versione_completa,
    SERVERPROPERTY('ProductVersion')           AS product_version,
    SERVERPROPERTY('ProductLevel')             AS product_level,
    SERVERPROPERTY('Edition')                  AS edizione,
    @@SERVERNAME                               AS istanza,
    DB_NAME()                                  AS database_corrente,
    SUSER_SNAME()                              AS login,
    USER_NAME()                                AS utente_db,
    SYSDATETIMEOFFSET()                        AS ora_server_con_offset,
    GETUTCDATE()                               AS ora_utc,
    d.compatibility_level,
    d.collation_name,
    d.recovery_model_desc,
    d.is_read_committed_snapshot_on
FROM sys.databases AS d
WHERE d.name = DB_NAME();

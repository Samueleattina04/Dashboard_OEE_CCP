-- Giro 2 - Database presenti sull'istanza (solo nomi): FDI potrebbe scrivere anche in un DB diverso da NETPRO.
SELECT name AS database_nome, create_date, state_desc, compatibility_level
FROM sys.databases
ORDER BY name;

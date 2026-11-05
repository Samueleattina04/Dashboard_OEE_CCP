<?php

namespace App\Mes;

use RuntimeException;

/**
 * Seconda linea di difesa (la prima sono i permessi dell'utente "lettore": db_datareader + db_denydatawriter).
 * Rifiuta qualunque istruzione che non sia una singola SELECT.
 */
final class GuardiaSolaLettura
{
    private const VIETATE = '/\b(INSERT|UPDATE|DELETE|MERGE|EXEC|EXECUTE|DROP|ALTER|CREATE|TRUNCATE|GRANT|REVOKE|DENY|BACKUP|RESTORE|DBCC|INTO|OPENROWSET|OPENQUERY|SHUTDOWN|KILL)\b/i';

    public static function verifica(string $sql): void
    {
        // Rimuove commenti e stringhe letterali prima dei controlli
        $pulita = preg_replace(["~--[^\n]*~", '~/\*.*?\*/~s', "~'(?:[^']|'')*'~", "~N'(?:[^']|'')*'~"], ' ', $sql);
        $pulita = trim($pulita);

        if (! preg_match('/^SELECT\b/i', $pulita)) {
            throw new RuntimeException('MES: ammesse solo istruzioni SELECT.');
        }
        if (str_contains(rtrim($pulita, "; \n\r\t"), ';')) {
            throw new RuntimeException('MES: ammessa una sola istruzione per chiamata.');
        }
        if (preg_match(self::VIETATE, $pulita, $m)) {
            throw new RuntimeException("MES: parola chiave non ammessa ({$m[1]}).");
        }
    }

    /** Le colonne sono inserite nel testo SQL: solo nomi della forma FVALUEnn / FNVALUEnn. */
    public static function colonnaValida(string $colonna): bool
    {
        return (bool) preg_match('/^F(N)?VALUE(0[1-9]|[1-9][0-9]|100)$/', $colonna);
    }
}

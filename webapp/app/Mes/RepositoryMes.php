<?php

namespace App\Mes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Unico punto di accesso al MES NetPro. SOLO LETTURA.
 * Query su indice cluster (FOBJECT, FOBJTYPE, FCHANNEL, FTIMESTAMP), colonne esplicite, parametri legati.
 * Ogni query eseguita è registrata nel canale di log "mes" (evidenza per la Fase 4).
 */
class RepositoryMes implements SorgenteCampioni, SorgenteNetProLive
{
    public function campioniDopo(string $macchina, array $colonne, ?string $dopoFtimestamp, ?string $finoA, int $limite): array
    {
        foreach ($colonne as $c) {
            if (! GuardiaSolaLettura::colonnaValida($c)) {
                throw new InvalidArgumentException("Colonna non ammessa: {$c}");
            }
        }
        $elenco = implode(', ', array_merge(['FCHANNEL', 'FTIMESTAMP', 'FROW', 'FBATCH'], $colonne));

        // FTIMESTAMP restituito come testo ISO (stile 121) per non perdere i millisecondi
        $sql = 'SELECT TOP ('.(int) $limite.') '
            .str_replace('FTIMESTAMP', 'CONVERT(varchar(23), FTIMESTAMP, 121) AS FTIMESTAMP', $elenco)
            .' FROM dbo.FPROCESSDATA'
            .' WHERE FOBJECT = ? AND FOBJTYPE = ? AND FCHANNEL = ?';
        $parametri = [$macchina, 'MAC', 'HIST'];

        if ($dopoFtimestamp !== null) {
            $sql .= ' AND FTIMESTAMP > CONVERT(datetime, ?, 121)';
            $parametri[] = $dopoFtimestamp;
        }
        if ($finoA !== null) {
            $sql .= ' AND FTIMESTAMP < CONVERT(datetime, ?, 121)';
            $parametri[] = $finoA;
        }
        $sql .= ' ORDER BY FTIMESTAMP, FROW';

        return $this->select($sql, $parametri);
    }

    /** Colonne di MACCHINE lette da "NetPro live" (stato e contatori; esclusi nomi di persone come RESPONSABILE). */
    public const COLONNE_STATO_MACCHINE = [
        'STATO_FISICO', 'ORA_ST_FISICO', 'STATO_LOGICO', 'ORA_ST_LOGICO', 'STATO_LINEE', 'TASK', 'TIPO_RILEVAZIONE',
        'FCODE01', 'FCODE02', 'VALORE_CONTATORE', 'TOTALE_CONTATORE', 'CONTATORE_SCARTI', 'TOTALE_CONTATORE_SCARTI',
        'FTOTQTY', 'FCOUNTSTATUS', 'PRODUZIONE', 'ORA_LETTURA', 'ORA_CONTATORE',
    ];

    /** Valore corrente di tutti i segnali (tabella piccola, ~60 righe, senza storico). */
    public function segnaliCorrenti(): array
    {
        return $this->select('SELECT FIOLINE, FOBJTYPE, FOBJECT, FDESCR, FTYPE, FSTATUS, FPOSITION, FVALUE,'
            .' CONVERT(varchar(23), FREADDATE, 121) AS FREADDATE FROM dbo.FIOLINES ORDER BY FOBJECT, FIOLINE', []);
    }

    /** Stato e contatori correnti di tutte le macchine (tabella piccola, ~26 righe). */
    public function statoMacchine(): array
    {
        $colonne = array_map(fn ($c) => str_starts_with($c, 'ORA_') ? "CONVERT(varchar(23), {$c}, 121) AS {$c}" : $c,
            self::COLONNE_STATO_MACCHINE);

        return $this->select('SELECT MACCHINA, DESCRIZIONE, CENTRO, '.implode(', ', $colonne)
            .' FROM dbo.MACCHINE ORDER BY MACCHINA', []);
    }

    /** Permessi effettivi dell'utente MES (evidenza di sola lettura) e ultimo campione per macchina. */
    public function verifica(array $macchine): array
    {
        $permessi = $this->select("SELECT permission_name COLLATE DATABASE_DEFAULT AS valore FROM fn_my_permissions(NULL, 'DATABASE')"
            .' UNION ALL SELECT r.name COLLATE DATABASE_DEFAULT FROM sys.database_role_members AS m'
            .' JOIN sys.database_principals AS r ON r.principal_id = m.role_principal_id'
            .' WHERE m.member_principal_id = DATABASE_PRINCIPAL_ID()', []);
        $ultimi = [];
        foreach ($macchine as $codice) {
            $r = $this->select('SELECT TOP (1) CONVERT(varchar(23), FTIMESTAMP, 121) AS FTIMESTAMP FROM dbo.FPROCESSDATA'
                .' WHERE FOBJECT = ? AND FOBJTYPE = ? AND FCHANNEL = ? ORDER BY FTIMESTAMP DESC', [$codice, 'MAC', 'HIST']);
            $ultimi[$codice] = $r[0]['FTIMESTAMP'] ?? null;
        }

        return ['permessi' => array_column($permessi, 'valore'), 'ultimi' => $ultimi];
    }

    /** @return array<int, array<string, mixed>> */
    protected function select(string $sql, array $parametri): array
    {
        GuardiaSolaLettura::verifica($sql);

        $inizio = microtime(true);
        $conn = DB::connection('mes');
        if (defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) {
            $conn->getPdo()->setAttribute(constant('PDO::SQLSRV_ATTR_QUERY_TIMEOUT'), (int) config('dashboard.mes_timeout_query', 30));
        }
        $righe = $conn->select($sql, $parametri);

        Log::channel('mes')->info('SELECT MES', [
            'sql' => $sql,
            'parametri' => $parametri,
            'righe' => count($righe),
            'ms' => (int) ((microtime(true) - $inizio) * 1000),
        ]);

        return array_map(fn ($r) => (array) $r, $righe);
    }
}

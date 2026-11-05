<#
.SYNOPSIS
  Fase 1 - Scoperta DB MES Netpro in SOLA LETTURA.
  Esegue gli script in .\sql e salva ogni risultato in .\output\<nome>.csv (separatore ";", UTF-8).

.NOTES
  - Usa solo System.Data.SqlClient (incluso in Windows PowerShell 5.1): nessun modulo da installare.
  - La password viene chiesta a video e NON viene salvata né scritta nei log.
  - ApplicationIntent=ReadOnly e "Application Name" permettono al DBA di riconoscere le connessioni.
  - Gli script contengono solo SELECT su cataloghi di sistema e TOP limitati su FProcessdata.
  - Lanciare da un PC/server che raggiunge 192.168.3.205 (es. 192.168.3.206).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\esegui_scoperta.ps1
  powershell -ExecutionPolicy Bypass -File .\esegui_scoperta.ps1 -Cartella sql_giro2
#>
param(
    [string]$Server   = '192.168.3.205\sistemi',
    [string]$Database = 'Netpro',
    [string]$Utente   = 'lettore',
    [int]   $TimeoutSecondi = 60,
    [string]$Cartella = 'sql'          # 'sql' = primo giro, 'sql_giro2' = secondo giro
)

$ErrorActionPreference = 'Stop'
$radice  = Split-Path -Parent $MyInvocation.MyCommand.Path
$cartSql = Join-Path $radice $Cartella
$cartOut = Join-Path $radice ('output_' + $Cartella)
New-Item -ItemType Directory -Force -Path $cartOut | Out-Null
$log = Join-Path $cartOut '_registro_esecuzione.txt'

$pwdSicura = Read-Host -AsSecureString "Password per l'utente $Utente"
$pwdSicura.MakeReadOnly()   # SqlCredential richiede una SecureString in sola lettura
$cred = New-Object System.Data.SqlClient.SqlCredential($Utente, $pwdSicura)

$cs = "Server=$Server;Database=$Database;ApplicationIntent=ReadOnly;Application Name=ASE-Dashboard-Scoperta;Connect Timeout=15"
$cn = New-Object System.Data.SqlClient.SqlConnection($cs, $cred)

"Esecuzione del $(Get-Date -Format 'dd/MM/yyyy HH:mm:ss') - server $Server - db $Database - utente $Utente" | Out-File $log -Encoding UTF8
try {
    $cn.Open()
    foreach ($f in Get-ChildItem $cartSql -Filter *.sql | Sort-Object Name) {
        $nome = [IO.Path]::GetFileNameWithoutExtension($f.Name)
        Write-Host "-> $nome"
        $inizio = Get-Date
        try {
            $cmd = $cn.CreateCommand()
            $cmd.CommandText = [IO.File]::ReadAllText($f.FullName)
            $cmd.CommandTimeout = $TimeoutSecondi
            $ds = New-Object System.Data.DataSet
            (New-Object System.Data.SqlClient.SqlDataAdapter($cmd)).Fill($ds) | Out-Null
            $i = 0
            foreach ($t in $ds.Tables) {
                $suffisso = if ($ds.Tables.Count -gt 1) { "_$i" } else { '' }
                $dest = Join-Path $cartOut "$nome$suffisso.csv"
                $t | Select-Object * -ExcludeProperty RowError, RowState, Table, ItemArray, HasErrors |
                    Export-Csv -Path $dest -Delimiter ';' -NoTypeInformation -Encoding UTF8
                $i++
            }
            $durata = [int]((Get-Date) - $inizio).TotalMilliseconds
            "OK    $nome  ($($ds.Tables.Count) risultati, $durata ms)" | Out-File $log -Append -Encoding UTF8
        }
        catch {
            Write-Warning "$nome : $($_.Exception.Message)"
            "ERRORE $nome : $($_.Exception.Message)" | Out-File $log -Append -Encoding UTF8
        }
    }
}
finally {
    $cn.Close()
}
Write-Host "Fatto. Comprimi la cartella '$cartOut' e inviala (verifica prima che non contenga dati sensibili)."

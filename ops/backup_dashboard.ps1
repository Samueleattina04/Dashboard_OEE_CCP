<#
.SYNOPSIS
  Backup giornaliero della Dashboard OEE: database MySQL dashboard_oee, log delle query MES, file .env.

.DESCRIPTION
  - Dump coerente del database (--single-transaction) compresso in ZIP.
  - Copia dei log storage\logs\mes-*.log (evidenza di sola lettura sul MES).
  - Copia del file .env (contiene password: la cartella di destinazione deve essere protetta).
  - Conservazione: elimina i backup più vecchi di $Giorni giorni.
  - Le credenziali MySQL NON sono in questo script: stanno in un file di opzioni MySQL protetto ($FileCredenziali).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File C:\ASE\Dashboard_OEE_CCP\ops\backup_dashboard.ps1
#>
param(
    [string]$Destinazione = 'C:\ASE\backup\dashboard_oee',
    [string]$App = 'C:\ASE\Dashboard_OEE_CCP\webapp',
    [string]$MysqlDump = '',
    [string]$FileCredenziali = 'C:\ASE\backup\mysql-backup.cnf',
    [string]$Database = 'dashboard_oee',
    [int]$Giorni = 30
)

$ErrorActionPreference = 'Stop'
$oggi = Get-Date -Format 'yyyy-MM-dd_HHmm'
$cartella = Join-Path $Destinazione $oggi
$registro = Join-Path $Destinazione 'backup.log'
New-Item -ItemType Directory -Force -Path $cartella | Out-Null

function Scrivi([string]$testo) {
    $riga = "$(Get-Date -Format 'dd/MM/yyyy HH:mm:ss')  $testo"
    Add-Content -Path $registro -Value $riga
    Write-Host $riga
}

try {
    if (-not $MysqlDump) {
        $trovato = Get-Command mysqldump -ErrorAction SilentlyContinue
        $MysqlDump = if ($trovato) { $trovato.Source } else {
            (Get-ChildItem 'C:\Program Files\MySQL' -Recurse -Filter mysqldump.exe -ErrorAction SilentlyContinue | Select-Object -First 1).FullName
        }
    }
    if (-not $MysqlDump -or -not (Test-Path $MysqlDump)) { throw 'mysqldump.exe non trovato: indicarlo con -MysqlDump' }
    if (-not (Test-Path $FileCredenziali)) { throw "File credenziali non trovato: $FileCredenziali" }

    # 1. Database
    $sql = Join-Path $cartella "$Database.sql"
    & $MysqlDump "--defaults-extra-file=$FileCredenziali" --single-transaction --routines --triggers --hex-blob `
        --default-character-set=utf8mb4 --databases $Database --result-file=$sql
    if ($LASTEXITCODE -ne 0) { throw "mysqldump terminato con codice $LASTEXITCODE" }
    Compress-Archive -Path $sql -DestinationPath "$sql.zip" -Force
    Remove-Item $sql
    Scrivi "OK database: $sql.zip ($([math]::Round((Get-Item "$sql.zip").Length / 1MB, 1)) MB)"

    # 2. Log delle query sul MES
    $log = Join-Path $App 'storage\logs'
    $mesLog = Get-ChildItem $log -Filter 'mes-*.log' -ErrorAction SilentlyContinue
    if ($mesLog) {
        Compress-Archive -Path $mesLog.FullName -DestinationPath (Join-Path $cartella 'log_mes.zip') -Force
        Scrivi "OK log MES: $($mesLog.Count) file"
    }

    # 3. Configurazione
    Copy-Item (Join-Path $App '.env') (Join-Path $cartella 'env.backup') -Force
    Scrivi 'OK .env'

    # 4. Conservazione
    $limite = (Get-Date).AddDays(-$Giorni)
    Get-ChildItem $Destinazione -Directory | Where-Object { $_.CreationTime -lt $limite } | ForEach-Object {
        Remove-Item $_.FullName -Recurse -Force
        Scrivi "Eliminato backup vecchio: $($_.Name)"
    }
    exit 0
}
catch {
    Scrivi "ERRORE: $($_.Exception.Message)"
    exit 1
}

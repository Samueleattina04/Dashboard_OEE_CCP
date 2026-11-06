# 03 — Installazione sul server 192.168.3.206 (Windows Server 2025)

La guida ha due parti:
- **Parte A, prima prova**: l'app gira con `php artisan serve` e un database SQLite (un file). Serve a vedere i dati reali senza installare MySQL e IIS.
- **Parte B, produzione**: MySQL, IIS con HTTPS, job pianificati. Da fare dopo la prova.

Tutti i comandi vanno eseguiti in **PowerShell come amministratore** sul server `.206`, salvo dove indicato.

---

## Parte A — Prima prova con `php artisan serve`

### A1. Prerequisiti da scaricare
Se il server non ha internet, scarica da un altro PC e copia i file sul server.

| Componente | Dove | Note |
|---|---|---|
| **PHP 8.3 x64 Non Thread Safe** (zip) | https://windows.php.net/download/ | Sezione "VS16 x64 Non Thread Safe" |
| **Visual C++ Redistributable 2015–2022 x64** | https://aka.ms/vs/17/release/vc_redist.x64.exe | Richiesto da PHP |
| **Microsoft ODBC Driver 18 for SQL Server x64** | https://learn.microsoft.com/sql/connect/odbc/download-odbc-driver-for-sql-server | Richiesto per collegarsi al MES |
| **Microsoft Drivers for PHP for SQL Server** (5.12 o successiva) | https://learn.microsoft.com/sql/connect/php/download-drivers-php-sql-server | Contiene `php_pdo_sqlsrv_83_nts_x64.dll` |
| **Composer** (Composer-Setup.exe) | https://getcomposer.org/download/ | Installa le librerie PHP |

### A2. Installare PHP
1. Installa il Visual C++ Redistributable e l'ODBC Driver 18 (avanti, avanti, fine).
2. Estrai lo zip di PHP in `C:\php`.
3. Dallo zip dei driver SQL Server copia `php_pdo_sqlsrv_83_nts_x64.dll` e `php_sqlsrv_83_nts_x64.dll` in `C:\php\ext`.
4. Crea il file di configurazione di PHP:
   ```bash
   Copy-Item C:\php\php.ini-production C:\php\php.ini
   ```
5. Apri `C:\php\php.ini` con il Blocco note e modifica queste righe, togliendo il `;` iniziale dove c'è:
   ```ini
   extension_dir = "ext"
   extension=bcmath
   extension=curl
   extension=fileinfo
   extension=gd
   extension=intl
   extension=mbstring
   extension=openssl
   extension=pdo_mysql
   extension=pdo_sqlite
   extension=sqlite3
   extension=zip
   extension=pdo_sqlsrv_83_nts_x64
   memory_limit = 512M
   date.timezone = Europe/Rome
   ```
6. Aggiungi PHP al PATH di sistema:
   ```bash
   [Environment]::SetEnvironmentVariable('Path', $env:Path + ';C:\php', 'Machine')
   ```
7. **Chiudi e riapri PowerShell**, poi verifica. Devi vedere `PHP 8.3.x`, e il secondo comando deve mostrare `pdo_sqlsrv`:
   ```bash
   php -v
   ```
   ```bash
   php -m | Select-String "pdo_sqlsrv|pdo_sqlite|intl|zip"
   ```
8. Installa Composer con `Composer-Setup.exe`, che troverà da solo `C:\php\php.exe`. Riapri PowerShell e verifica:
   ```bash
   composer -V
   ```

### A3. Copiare l'applicazione
1. Scarica lo ZIP del branch, come per gli script di scoperta:
   https://github.com/samueleattina04/Dashboard_OEE_CCP/archive/refs/heads/claude/friendly-heisenberg-wbfsx1.zip
2. Estrailo e sposta la cartella `webapp` in `C:\ASE\dashboard`:
   ```bash
   Expand-Archive "$env:USERPROFILE\Downloads\Dashboard_OEE_CCP-claude-friendly-heisenberg-wbfsx1.zip" -DestinationPath C:\ASE\sorgenti -Force
   ```
   ```bash
   Copy-Item C:\ASE\sorgenti\Dashboard_OEE_CCP-claude-friendly-heisenberg-wbfsx1\webapp C:\ASE\dashboard -Recurse
   ```
   ```bash
   cd C:\ASE\dashboard
   ```
   ```bash
   Get-ChildItem -Recurse | Unblock-File
   ```
3. Installa le librerie PHP (servono internet e qualche minuto):
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

### A4. Configurare l'app (`.env`)
1. Crea il file di configurazione e la chiave dell'app:
   ```bash
   Copy-Item .env.example .env
   ```
   ```bash
   php artisan key:generate
   ```
2. Apri `C:\ASE\dashboard\.env` con il Blocco note e imposta i valori seguenti. Per la prova si usa SQLite; le righe `DB_HOST`, `DB_PORT`, `DB_USERNAME` e `DB_PASSWORD` si possono lasciare come sono, vengono ignorate.
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=http://192.168.3.206:8000

   DB_CONNECTION=sqlite
   DB_DATABASE=C:\ASE\dashboard\database\database.sqlite

   MES_HOST='192.168.3.205\sistemi'
   MES_PORT=
   MES_DATABASE=NETPRO
   MES_USERNAME=lettore
   MES_PASSWORD=la_password_di_lettore

   MAIL_MAILER=log
   ```
   - `MAIL_MAILER=log` significa che, finché la posta non è configurata, gli inviti non partono: il link compare a video al SuperAdmin.
   - **La password sta solo in questo file**, che non va condiviso né copiato altrove.
3. Proteggi il file `.env`, così che lo leggano solo gli amministratori e l'utente di sistema:
   ```bash
   icacls .env /inheritance:r /grant:r "Administrators:F" "SYSTEM:F"
   ```

### A5. Creare il database e il SuperAdmin
1. Crea il file del database SQLite:
   ```bash
   New-Item database\database.sqlite -ItemType File -Force
   ```
2. Crea le tabelle e inserisci le macchine con le regole di stato decise:
   ```bash
   php artisan migrate --seed --force
   ```
3. Crea il SuperAdmin. Ti chiederà la password: almeno 12 caratteri, con maiuscole, minuscole, numeri e simboli.
   ```bash
   php artisan utenti:crea-superadmin administrator@pisti.it --nome=Administrator
   ```
   L'email è un esempio: usa quella che preferisci.

### A6. Collegare il MES e caricare i dati
1. **Verifica della connessione**: è solo lettura. Deve rispondere "Connessione OK", mostrare `db_datareader` e `db_denydatawriter` e l'ultimo campione di FX10130 e FORNOKING500.
   ```bash
   php artisan mes:verifica
   ```
   Se compare un errore di certificato o di crittografia, metti `MES_ENCRYPT=no` nel `.env` e riprova.
2. **Storico** degli ultimi 12 mesi, a blocchi mensili: circa 35.000 righe per macchina, letture su indice. Meglio lanciarlo fuori dall'orario di punta:
   ```bash
   php artisan mes:storico FORNOKING500 --da=2025-11-01
   ```
   ```bash
   php artisan mes:storico FX10130 --da=2025-11-01
   ```
3. **Aggiornamento**, cioè i soli campioni nuovi:
   ```bash
   php artisan mes:importa
   ```

### A7. Avviare l'app e aprirla
1. Apri la porta 8000 solo verso la rete interna:
   ```bash
   New-NetFirewallRule -DisplayName "Dashboard OEE (prova)" -Direction Inbound -Protocol TCP -LocalPort 8000 -RemoteAddress 192.168.3.0/24 -Action Allow
   ```
2. Avvia il server di prova. La finestra va lasciata aperta:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
3. Da un PC della rete apri **http://192.168.3.206:8000** e accedi con il SuperAdmin.
4. Per l'aggiornamento automatico ogni 15 minuti, apri **una seconda finestra** PowerShell in `C:\ASE\dashboard` e lancia:
   ```bash
   php artisan schedule:work
   ```

### A8. Primi passi nell'app
1. **Configurazione → Calendari dei turni**: crea il calendario, aggiungi turni e pause, poi assegnalo alle macchine. Serve per la Disponibilità.
2. **Configurazione → Capacità nominale** di FX10130 in kg/h. Serve per la Prestazione.
3. **Utenti**: invita Qualità e Direzione. Finché la posta non è configurata, il link d'invito compare a video e va consegnato a mano.

> `php artisan serve` è **solo per la prova**: è HTTP non cifrato, monoutente e si chiude con la finestra. Per l'uso quotidiano segui la Parte B.

---

## Parte B — Produzione (configurazione realizzata il 06/11/2026)

**Database**: MySQL 8.4 già presente sul server, database `dashboard_oee` (da usare con un utente dedicato, non `root`).

**Scheduler** (Utilità di pianificazione, account SYSTEM): attività "Dashboard OEE - scheduler" con due trigger, all'avvio e "da adesso", entrambi ripetuti ogni minuto. Esegue `C:\php\php.exe artisan schedule:run` nella cartella `C:\ASE\Dashboard_OEE_CCP\webapp`.
```bash
$php = "C:\php\php.exe"; $app = "C:\ASE\Dashboard_OEE_CCP\webapp"; $azione = New-ScheduledTaskAction -Execute $php -Argument "artisan schedule:run" -WorkingDirectory $app; $avvio = New-ScheduledTaskTrigger -AtStartup; $avvio.Repetition = (New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)).Repetition; $ora = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1); $impostazioni = New-ScheduledTaskSettingsSet -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -MultipleInstances IgnoreNew -StartWhenAvailable; Register-ScheduledTask -TaskName "Dashboard OEE - scheduler" -Action $azione -Trigger $avvio, $ora -Settings $impostazioni -User "SYSTEM" -RunLevel Highest -Force
```
Verifica (`NextRunTime` deve essere valorizzato):
```bash
Get-ScheduledTaskInfo -TaskName "Dashboard OEE - scheduler" | Select-Object LastRunTime, LastTaskResult, NextRunTime
```

**IIS**: pool `DashboardOEE` (senza .NET), sito "Dashboard OEE" su `*:8010`, cartella `C:\ASE\Dashboard_OEE_CCP\webapp\public`. PHP via FastCGI `C:\PHP\php-cgi.exe` (configurazione del server). `public\web.config` imposta `index.php` come pagina predefinita e la riscrittura degli indirizzi (modulo URL Rewrite).
```bash
Import-Module WebAdministration
```
```bash
New-WebAppPool -Name "DashboardOEE"
```
```bash
Set-ItemProperty IIS:\AppPools\DashboardOEE -Name managedRuntimeVersion -Value ""
```
```bash
New-Website -Name "Dashboard OEE" -Port 8010 -PhysicalPath "C:\ASE\Dashboard_OEE_CCP\webapp\public" -ApplicationPool "DashboardOEE"
```
Permessi: lettura su tutta l'app, scrittura solo su `storage` e `bootstrap\cache`, lettura sul `.env`.
```bash
icacls "C:\ASE\Dashboard_OEE_CCP\webapp" /grant "IIS AppPool\DashboardOEE:(OI)(CI)RX" /T /Q
```
```bash
icacls "C:\ASE\Dashboard_OEE_CCP\webapp\storage" /grant "IIS AppPool\DashboardOEE:(OI)(CI)M" /T /Q
```
```bash
icacls "C:\ASE\Dashboard_OEE_CCP\webapp\bootstrap\cache" /grant "IIS AppPool\DashboardOEE:(OI)(CI)M" /T /Q
```
```bash
icacls "C:\ASE\Dashboard_OEE_CCP\webapp\.env" /grant "IIS AppPool\DashboardOEE:R"
```

**Posta**: invio diretto a `pisti-it.mail.protection.outlook.com:25` (solo destinatari del tenant Microsoft 365). Resta da aggiungere l'IP pubblico `217.58.204.66` al record SPF di `pisti.it`.

**HTTPS** (07/11/2026): indirizzo **https://dashboard-oee.ase.intranet.nutkao.it**.
- Voce DNS di tipo A `dashboard-oee` → `192.168.3.206`, nella zona `ase.intranet.nutkao.it`.
- Certificato autofirmato valido 5 anni (`New-SelfSignedCertificate`), parte pubblica in `C:\ASE\dashboard-oee.cer`.
- Collegamento IIS `https`, porta 443, con SNI (`-SslFlags 1`).
- `.env`: `APP_URL=https://dashboard-oee.ase.intranet.nutkao.it` e `SESSION_SECURE_COOKIE=true`.
- Per togliere l'avviso del browser, distribuire `dashboard-oee.cer` come "Autorità di certificazione radice attendibile" tramite GPO.

**Da completare**:
- utente MySQL dedicato;
- backup giornaliero (mysqldump) di `dashboard_oee` e dei log `storage\logs\mes-*.log`.

## Aggiornamento dopo un `git pull`

```bash
cd C:\ASE\Dashboard_OEE_CCP
```
```bash
git pull
```
```bash
cd webapp
```
```bash
composer install --no-dev --optimize-autoloader
```
```bash
php artisan migrate --force
```
```bash
php artisan optimize:clear
```
```bash
php artisan config:cache
```
```bash
php artisan route:cache
```
```bash
php artisan view:cache
```
```bash
Restart-WebAppPool -Name "DashboardOEE"
```

## Comandi utili

| Comando | A cosa serve |
|---|---|
| `php artisan mes:verifica` | Test della connessione e dei permessi (sola lettura) |
| `php artisan mes:importa` | Importa i nuovi campioni (lo fa il job ogni 15 min) |
| `php artisan mes:storico <MACCHINA> --da=AAAA-MM-GG` | Carica lo storico a blocchi mensili |
| `php artisan kpi:ricalcola --da=AAAA-MM-GG` | Ricalcola gli aggregati dal DB della dashboard (non legge il MES) |
| `php artisan utenti:crea-superadmin <email>` | Crea o ripristina il SuperAdmin |

Il registro di **tutte le query eseguite sul MES** è in `storage\logs\mes-AAAA-MM-GG.log` e serve come evidenza di sola lettura in Fase 4.

---

## Backup giornaliero

Lo script `ops\backup_dashboard.ps1` salva ogni notte:
- il database `dashboard_oee`, compresso in ZIP;
- i log delle query sul MES;
- il file `.env`.

I backup finiscono in `C:\ASE\backup\dashboard_oee\<data>` e vengono conservati 30 giorni.

**1. Cartella protetta** (solo amministratori e SYSTEM, perché contiene il `.env` con le password):
```bash
New-Item -ItemType Directory -Force C:\ASE\backup\dashboard_oee | Out-Null
```
```bash
icacls C:\ASE\backup /inheritance:r /grant:r "Administrators:(OI)(CI)F" "SYSTEM:(OI)(CI)F"
```

**2. File con le credenziali MySQL**, letto da `mysqldump` (la password non sta nello script). Aprilo:
```bash
notepad C:\ASE\backup\mysql-backup.cnf
```
e scrivi, con l'utente della dashboard (non `root`):
```ini
[client]
user=dashboard
password=LA_PASSWORD
host=127.0.0.1
```

**3. Prova manuale:**
```bash
powershell -ExecutionPolicy Bypass -File C:\ASE\Dashboard_OEE_CCP\ops\backup_dashboard.ps1
```

**4. Attività pianificata** ogni notte alle 01:40, con l'account di sistema:
```bash
$azione = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -ExecutionPolicy Bypass -File C:\ASE\Dashboard_OEE_CCP\ops\backup_dashboard.ps1"; $quando = New-ScheduledTaskTrigger -Daily -At 01:40; Register-ScheduledTask -TaskName "Dashboard OEE - backup" -Action $azione -Trigger $quando -User "SYSTEM" -RunLevel Highest -Force
```

**Ripristino** (es. su un database vuoto):
```bash
Expand-Archive C:\ASE\backup\dashboard_oee\<data>\dashboard_oee.sql.zip -DestinationPath C:\ASE\ripristino
```
```bash
cmd /c "mysql --defaults-extra-file=C:\ASE\backup\mysql-backup.cnf < C:\ASE\ripristino\dashboard_oee.sql"
```
Il dump contiene `CREATE DATABASE` e sovrascrive le tabelle di `dashboard_oee`. Per una prova senza toccare quello in uso, ripristina su un altro server o rinomina il database nel file.

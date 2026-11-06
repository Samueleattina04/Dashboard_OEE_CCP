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

## Parte B — Produzione (dopo la prova)

1. **MySQL 8.4 LTS** per Windows, installato come servizio:
   - crea il database `dashboard_oee` (utf8mb4) e un utente dedicato con permessi solo su quel database;
   - nel `.env` imposta `DB_CONNECTION=mysql` e le credenziali, poi esegui `php artisan migrate --seed --force` e ricarica lo storico (A6).
2. **IIS** con FastCGI per PHP:
   - sito con cartella fisica `C:\ASE\dashboard\public`, modulo URL Rewrite (il file `web.config` di Laravel è in `public`);
   - **HTTPS** con il certificato della CA interna; chiudere la porta 8000.
   - permessi di scrittura per l'utente del pool IIS solo su `storage` e `bootstrap\cache`.
3. **Job pianificati**: nell'Utilità di pianificazione di Windows crea un'attività che ogni minuto esegue `C:\php\php.exe C:\ASE\dashboard\artisan schedule:run`, con un utente di servizio dedicato. L'attività avvia `mes:importa` ogni 15 minuti e `kpi:ricalcola` ogni notte alle 02:10.
4. **Posta**: impostare `MAIL_MAILER=smtp` e i parametri della casella aziendale per gli inviti.
5. **Backup**: dump giornaliero di MySQL con `mysqldump` pianificato, più una copia di `.env` (in luogo protetto) e di `storage\logs\mes*.log`, che sono il registro delle query sul MES.
6. `APP_ENV=production`, `APP_DEBUG=false`, poi:
   ```bash
   php artisan config:cache
   ```
   ```bash
   php artisan route:cache
   ```
   ```bash
   php artisan view:cache
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

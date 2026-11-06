# Fase 4 — Verifica: checklist

Aggiornata al 06/11/2026. Obiettivo: dimostrare che i numeri della dashboard sono **corretti**, **tracciabili**
e ottenuti **senza scrivere sul MES**, prima della consegna e dell'uso quotidiano.

Legenda: 👤 = fai tu (o la persona indicata) · 🤖 = faccio io · 🤝 = insieme

---

## 0. Prerequisiti (da chiudere prima di iniziare)

- [x] 👤 App pubblicata su **IIS** (porta 8010) e **scheduler automatico** attivo — fatto il 06/11/2026.
- [ ] 👤 Almeno 7 giorni di funzionamento continuo prima della riconciliazione.
- [ ] 👤 **Calendario turni** reale inserito e assegnato a FORNOKING500 e FX10130.
- [ ] 👤 **Capacità nominale** di FX10130 (kg/h) inserita.
- [ ] 👤 Risposta di 3UVision sul significato di **`End3`** (oppure decisione di lasciarlo "non classificato" anche in verifica).
- [ ] 🤝 Decisione sulla **Qualità** di FX10130 (oppure accettare OEE = n.d. per questa fase).

## 1. Test automatici

Già presenti 23 test (calcoli con casi fatti a mano, permessi per ruolo, inviti, password).

- [ ] 🤖 Aggiungere i test che il prompt richiede e non sono ancora coperti:
  - [ ] limiti CCP (valore pari al limite, sopra, sotto, misura mancante), insieme alle viste CCP;
  - [x] export: formati italiani (`;`, virgola decimale, `gg/mm/aaaa`) verificati sul contenuto;
  - [x] un giorno con cambio dell'ora legale (25 ore) completo, dall'importazione all'aggregato (ha trovato e fatto correggere un difetto nell'importazione incrementale dell'ora ripetuta).
- [ ] 🤖 Eseguire tutti i test e allegare l'esito a `docs/04_riconciliazione.md`.
- [ ] 👤 Eseguire gli stessi test **sul server** (con `composer install` *senza* `--no-dev`, solo per la prova) e confermare che passano anche lì.

## 2. Riconciliazione con i dati di origine

Il prompt chiede il confronto con i **report nativi del MES**. NetPro però non consuntiva la produzione (Fase 1), quindi
il riferimento va scelto tra quelli disponibili.

- [ ] 👤 **Scegliere 3 giornate** di riferimento (es. una giornata piena, una con fermi, una con avvio/riscaldamento del forno).
- [ ] 👤 **Macchine**: FORNOKING500 e FX10130 (HYPERSORTL4 e BROVRAF fuori perimetro: 2 macchine invece di 3).
- [ ] 🤝 **Scegliere il riferimento** per ciascuna giornata (almeno uno):
  - [ ] grafici o storico del client **NetPro** per quelle macchine e quelle date;
  - [ ] **registro di produzione** o fogli dei capoturno (orari di accensione e spegnimento, kg lavorati);
  - [ ] **conteggio manuale** dei campioni `FPROCESSDATA` del giorno (query di sola lettura che preparo io);
  - [ ] per FX10130, i contatori kg visti sul pannello della macchina a inizio e fine giornata.
- [ ] 👤 **Scostamento ammesso**: decidere la soglia (es. ±1 punto percentuale sulla Disponibilità; ±15 min sulle ore in funzione, pari alla risoluzione dei dati).
- [x] 🤖 Preparare la riconciliazione: comando `php artisan kpi:riconcilia <MACCHINA> <AAAA-MM-GG> [--dettaglio]`, che rilegge FPROCESSDATA e ricalcola in modo indipendente.
- [ ] 🤝 Confrontare, per ogni giornata e macchina:
  - [ ] ore in funzione, ferma, riscaldamento/raffreddamento (forno), senza dato;
  - [ ] numero di campioni presenti / attesi;
  - [ ] Disponibilità (con il calendario turni);
  - [ ] kg selezionati/scartati e Prestazione (FX10130).
- [ ] 🤖 Scrivere **`docs/04_riconciliazione.md`**: valori dashboard, valori di riferimento, scostamento, spiegazione di ogni differenza fuori soglia.
- [ ] 🤝 Correggere regole o soglie se la riconciliazione mostra errori sistematici, poi ripetere.

## 3. Controllo di sola lettura sul MES

- [x] Permessi dell'utente `lettore` verificati: `CONNECT`, `SELECT`, `db_datareader`, `db_denydatawriter`, nessun permesso di scrittura (`php artisan mes:verifica`, 06/11/2026).
- [ ] 🤖 Allegare a `docs/04_riconciliazione.md` l'output di `mes:verifica` e un estratto di `storage\logs\mes-AAAA-MM-GG.log`, il registro di **tutte** le query eseguite sul MES: solo `SELECT` su `FPROCESSDATA` e cataloghi.
- [ ] 👤 (Facoltativo, più forte) Chiedere all'IT un'estrazione dei permessi dell'utente `lettore` dal lato SQL Server, o un trace/Extended Events di 24 ore sulle sessioni con `Application Name` della dashboard.
- [ ] 🤖 Documentare la **seconda linea di difesa** nel codice: guardia che accetta solo `SELECT`, con relativo test.

## 4. Sicurezza (NIS2) prima della consegna

- [ ] 👤 **HTTPS** su IIS con certificato della CA interna e nome DNS (es. `dashboard-oee.ase.local`); chiusura dell'HTTP.
- [ ] 👤 **Utente MySQL dedicato** (non `root`) con permessi solo su `dashboard_oee`.
- [ ] 👤 `.env` leggibile solo da amministratori, SYSTEM e pool IIS; accesso alle cartelle `config`/`xml` di FDI limitato.
- [ ] 👤 **Backup** giornaliero: script pronto (`ops\backup_dashboard.ps1`, istruzioni in `docs/03_installazione.md`), da attivare e da provare con un ripristino.
- [ ] 🤖 Verificare che log di accessi ed export registrino chi, cosa e quando; esempio allegato alla documentazione.
- [ ] 🤖 Dipendenze con versioni bloccate (`composer.lock`) e controllo vulnerabilità (`composer audit`).

## 5. Consegna

- [ ] 🤖 `README.md` completo: installazione, configurazione `.env`, avvio, aggiornamento con `git pull`, backup e ripristino.
- [ ] 🤖 **Manuale utente** breve: 1 pagina per la **Qualità**, 1 pagina per la **Direzione** — bozza in `docs/05_manuale_utente.md`, da rifinire dopo le prove.
- [ ] 🤖 Elenco finale dei **`[DA VERIFICARE]`** rimasti aperti.
- [ ] 🤝 Unire il ramo di lavoro al ramo principale e far puntare il server lì.
- [ ] 👤 Presentazione ai primi utenti (Qualità e Direzione) e raccolta delle osservazioni.

---

## Prima della Fase 4: sviluppi della Fase 3 ancora aperti

Da completare (o rinviare esplicitamente) prima della verifica finale:

- [x] 🤖 Vista **Storico e confronti** (periodo contro periodo, macchina contro macchina), con export.
- [ ] 🤝 **CCP**: definizione con la Qualità, poi viste, carte di controllo, registro deviazioni, misure mancanti e **report PDF** per audit.
- [ ] 🤖 **Causali di fermo e 6 big losses**, quando ci saranno le registrazioni manuali (oggi parcheggiate).

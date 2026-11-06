# Checklist punti aperti — Dashboard OEE e CCP (ASE)

Aggiornata al 06/11/2026. Spunta le voci man mano e fammi sapere l'esito.
Legenda: 🔴 urgente / blocca qualcosa · 🟠 importante · 🟢 quando possibile

---

## A. Sicurezza (da fare subito)

- [x] 🔴 **Cambiare la password di `root` di MySQL** — fatto il 06/11/2026. sul server .206: è stata mostrata in chiaro durante l'installazione. Prima verifica quali altre app usano `root`, perché andrà aggiornata anche nei loro `.env`.
- [ ] 🔴 **Utente MySQL dedicato per la dashboard** (`dashboard`, permessi solo su `dashboard_oee`) al posto di `root` (minimo privilegio, NIS2).
- [x] 🔴 **Credenziali SQL in chiaro nei config FDI** (anomalia A8) — password cambiate il 06/11/2026; resta da limitare l'accesso alle cartelle `config`/`xml`.: gli utenti `netpro` e `FDI` hanno password banali e permessi di scrittura sul MES, e le password sono leggibili nei file `C:\Qualitas\NetPro\FDI\Apps\AppDBI_*\xml\Config.xml`. Da far cambiare (con Qualitas) e limitare l'accesso alle cartelle `config`/`xml`.
- [ ] 🟠 Verificare che il file `.env` della dashboard sia leggibile solo da amministratori (`icacls`, già indicato).

## B. Configurazione nella dashboard (la fai tu, dal menu Configurazione)

- [ ] 🔴 **Calendario turni reale**: turni, pause e giorni lavorativi di FORNOKING500 e FX10130; assegnare il calendario alle macchine. *Senza questo la Disponibilità resta "n.d."*
- [ ] 🔴 **Capacità nominale di FX10130** in kg/h. *Senza questa la Prestazione resta "n.d."*
- [ ] 🟠 **Festivi e fermi programmati** (eccezioni del calendario), almeno per i prossimi mesi.
- [ ] 🟢 **Target** di OEE e Disponibilità per macchina, se volete il confronto con un obiettivo.
- [ ] 🟠 **Invitare gli utenti** di Qualità e Direzione (menu Utenti). Finché la posta non è configurata, il link va consegnato a mano.

## C. Da verificare guardando i dati (mi fai sapere)

- [ ] 🔴 **Ore in funzione del forno**: confrontare alcune giornate note (es. una settimana recente) con quanto ha lavorato davvero. Soglie attuali: in funzione ≥ 100 °C, transitorio 70–100 °C, fermo < 70 °C.
- [ ] 🟠 **Fascia 90–100 °C del forno**: ci sono molti campioni in questa fascia. Esiste una ricetta (es. asciugatura) che lavora sotto i 100 °C? Se sì, oggi viene contata come riscaldamento/raffreddamento.
- [ ] 🟠 **Ore in funzione di FX10130** su qualche giornata nota (regole: `Selecting` e `Cleaning` = in funzione, `Ready` = ferma, `…Error` = allarme).
- [ ] 🟠 **Kg selezionati/scartati di FX10130**: sono plausibili rispetto alla produzione reale? (Il manuale li dichiara "indicativi, non fiscali".)
- [ ] 🟢 Leggibilità dell'interfaccia su PC e tablet di reparto: segnalami cosa cambiare.

## D. Decisioni ancora aperte

- [ ] 🟠 **Qualità di FX10130**: in una selezionatrice lo scarto è il suo lavoro. Cosa consideriamo "non conforme" ai fini OEE? *Finché non si decide, OEE di FX10130 = n.d.*
- [ ] 🟠 **Significato dello stato `End3`** di FX10130 (≈ 1.050 ore in 12 mesi, oggi "non classificato"). Da chiedere a 3UVision.
- [ ] 🟢 **MTBF/MTTR**: restano nascosti finché i dati sono a 15 minuti. Confermi?
- [ ] 🟢 **Linee**: per ora OEE per singola macchina. Servirà un raggruppamento in linee in futuro?

## E. Da chiedere a terzi

**Qualitas (fornitore MES/FDI)**
- [ ] 🟠 **FX10130 smette di inviare dati per lunghi periodi**: nessun record da febbraio a maggio 2026, mesi parziali dopo, ultimo campione il 04/11/2026 09:15. Perché il connettore OPC perde la comunicazione?
- [ ] 🟠 **A2 — Formula scarti FX10130**: in `RejectedProductKg` sembra mancare il `+` tra sezione 1 e 2 (`Server\FDI_OPC\config\Config.json`).
- [ ] 🟠 **A9 — Forno**: la variabile che FDI chiama `STATO_MACCHINA` è in realtà `STATO_ORDINE` del PLC; il forno non trasmette uno stato marcia/fermo.
- [ ] 🟠 **A10 — FX10130**: FDI calcola lo stato da `Active` ("hardware riconosciuto") invece che da `CurrentSmState`.
- [ ] 🟢 **HYPERSORTL4**: record vuoti dal 28/01/2026 (A1). Fuori perimetro, ma da segnalare.
- [ ] 🟢 **BROVRAF**: valori a 0 dal 13/05/2025 (A3).
- [ ] 🟢 **Frequenza di registrazione**: si può passare da 15 minuti a 1 minuto (almeno per il forno) e storicizzare `Time_Permanence`, `BulkHead_Opening` e ricetta? Attenzione al limite di 10 GB di SQL Server Express.
- [ ] 🟢 A12 — Indirizzo PLC forno: il documento Brovind dice `11.11.11.31`, il config FDI `11.11.11.11`.

**3UVision**
- [ ] 🟠 Significato di `End3`, `CleaningMachine`, `Testing`, `ReadyCheck`, `OfflineOrDisabled` (non presenti nel manuale).

**Brovind**
- [ ] 🟢 Il supervisore del forno salva ancora in locale l'archivio mensile (`ArchiveKing800_*.csv`, fermo su NAS a luglio 2022)? Utile per i CCP di tostatura.

## F. CCP (dopo i primi dati OEE, con la Qualità)

- [ ] Quali sono i CCP e su quali macchine.
- [ ] La temperatura di tostatura (`TTOST`) e la temperatura prodotto sono CCP? Con quali limiti critici e di allerta, eventualmente per ricetta?
- [ ] Un campione ogni 15 minuti è sufficiente come registrazione CCP?
- [ ] Serve il tempo di permanenza (`Time_Permanence`, oggi non storicizzato)?
- [ ] Un valore pari al limite è conforme? (Proposta: sì.)
- [ ] `Q_ElencoNC3tre.xlsx` (NAS, cartella PowerBILink) è il registro delle non conformità? Contiene anche le deviazioni CCP?
- [ ] Power BI è già usato su questi dati? La dashboard deve affiancarlo o sostituirlo?

## G. Sviluppi ancora da fare (a cura mia)

- [ ] 🟠 Vista **Storico e confronti** (periodo contro periodo, macchina contro macchina).
- [ ] 🟠 **Viste CCP**: carta di controllo con limiti, registro deviazioni e azioni correttive, misure mancanti.
- [ ] 🟠 **Report PDF CCP** per audit (intestazione ASE, filtri, data di estrazione, utente).
- [ ] 🟢 **Causali di fermo e 6 big losses**: la struttura è pronta, la vista arriverà con le registrazioni manuali.
- [ ] 🟢 **Registrazioni manuali degli operatori** (timestamp disponibili): esplorazione parcheggiata.
- [ ] 🟢 Manuale utente breve (1 pagina Qualità, 1 pagina Direzione).

## H. Messa in produzione (dopo la prova)

- [ ] 🟠 **IIS** con sito su `C:\ASE\Dashboard_OEE_CCP\webapp\public` e **HTTPS** con certificato della CA interna; poi chiudere la porta 8010.
- [ ] 🟠 **Utilità di pianificazione**: `php artisan schedule:run` ogni minuto (sostituisce la finestra `schedule:work`).
- [ ] 🟠 **Posta SMTP** per gli inviti: quale casella aziendale usare?
- [ ] 🟠 **Backup** giornaliero di `dashboard_oee` (mysqldump) e dei log `storage\logs\mes-*.log`.
- [ ] 🟢 `APP_ENV=production` e cache di config, route e viste.
- [ ] 🟢 Unire il ramo di lavoro al ramo principale e far puntare il server lì.

## I. Fase 4 — Verifica

- [ ] Scegliere **3 giornate** (e le macchine) da riconciliare con i report nativi del MES.
- [ ] Definire lo **scostamento ammesso** (es. ±1 punto percentuale).
- [ ] Evidenza di sola lettura: permessi di `lettore` già verificati (`db_datareader` + `db_denydatawriter`) e log delle query in `storage\logs\mes-*.log`.

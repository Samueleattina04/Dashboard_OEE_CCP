# Fase 1 — Piano di scoperta del database MES

Stato: **in corso** — in attesa dell'output degli script `discovery/`.

## Contesto acquisito

| Voce | Valore | Note |
|---|---|---|
| MES | Qualitas – NetPro | |
| Istanza DB | `192.168.3.205\sistemi`, database `Netpro` | La sintassi `host\istanza` indica **Microsoft SQL Server** (istanza nominata). Versione da confermare con `00_info_server`. `[DA VERIFICARE]` |
| Utente DB | `lettore` (sola lettura) | Ruoli/permessi effettivi verificati da `01_permessi_utente` |
| Server applicativo | `192.168.3.206`, Windows Server 2025 | Stessa subnet del MES |
| Tabella storico macchine | `FProcessdata`, macchina in `FObject` | Significato delle altre colonne per macchina `[DA VERIFICARE]` |
| Macchine attese | `BROVRAF`, `FORNOKING500`, `FX10130`, `HYPERSORTL4` | |
| Autenticazione | Locale: SuperAdmin invita via email, l'utente imposta la password al primo accesso | Sostituisce Entra ID |

## Cosa emerge dall'elenco directory FDI (`\\192.168.3.205\c$\Qualitas\NetPro\FDI`)

FDI è lo strato di raccolta dati di NetPro: servizi Windows che leggono le macchine e scrivono su SQL.

- **Server** (`FDI_DBI`, `FDI_OPC`, `FDI_Siemens`): servizi centrali con `config\Config.json` / `xml\Config.xml`
  (il `Config.json` di `FDI_OPC` è di ~124 KB, quindi contiene molte definizioni) e un endpoint `WS_RESTFUL.url`.
- **Apps / DeviceApp** (un connettore per macchina o fornitore):
  - OPC UA: `3UVISION`, `Cimbria`, `HEUFT`, `PELabellers`, `Seydelmann`, `SILOSCR`, `ULMA`
  - Siemens S7: `Brovind_Forno`, `Brovind_Pasta`
  - Modbus: `Modbus01`, `Modbus02`
  - DBI (import da file o DB): `AppDBI_Browind`, `AppDBI_FMT` (CSV in/out), `AppDBI_Silos` (CSV giornalieri PP-Sxx)
- Ogni connettore ha un `Config.json`/`Config.xml`. Con buona probabilità questo file è
  **la mappa "variabile PLC → colonna di FProcessdata"** citata nel prompt. Se è così, è il documento
  più utile di tutta la Fase 1: dice cosa rappresenta ogni colonna per ogni macchina.

### Possibile corrispondenza macchine ↔ connettori `[DA VERIFICARE]`

Ipotesi basate solo sui nomi. **Non verranno usate finché non le confermate.**

| Macchina | Connettore candidato | Dubbio |
|---|---|---|
| BROVRAF | `DeviceAppSiemens_Brovind_*` / `AppDBI_Browind` | "RAF" = raffreddatore? |
| FORNOKING500 | `DeviceAppSiemens_Brovind_Forno`? | forno di tostatura → **CCP temperatura/tempo** |
| FX10130 | ? | nessun nome evidente |
| HYPERSORTL4 | `DeviceAppOPC_Cimbria` o `3UVISION`? | selezionatrice ottica |

## Piano (Fase 1)

1. Eseguire gli script `discovery/sql/00–10` (solo metadati + TOP limitati su FProcessdata) dal server `.206`.
2. Analizzare versione, permessi effettivi (prova della sola lettura), tabelle, colonne, indici e viste.
3. Analizzare i `Config.json`/`Config.xml` dei connettori FDI per ricostruire il mapping variabile → colonna.
4. Secondo giro di query mirate: campione di 20 righe **per macchina** su FProcessdata (filtro su FObject e
   data, usando gli indici reali) e campioni delle altre tabelle candidate (anagrafiche, stati/fermi, ordini, CCP).
5. Classificare le tabelle (anagrafiche, eventi macchina, produzione, CCP).
6. Individuare le lacune: tempo ciclo teorico, causali di fermo, turni, limiti CCP.
7. Redigere `docs/01_mappa_db.md` con il diagramma ER in Mermaid, le colonne chiave, le lacune e le domande.
8. **STOP**: attendere l'approvazione.

## Domande aperte

1. Potete inviare i `Config.json` / `Config.xml` di `FDI_DBI`, `FDI_OPC`, `FDI_Siemens` e dei DeviceApp
   relativi alle 4 macchine? **Prima oscurate password, stringhe di connessione e certificati.**
2. Esiste già il documento "variabile recuperata → colonna utilizzata" per ogni macchina citato nel prompt? Se sì, inviatelo.
3. Le 4 macchine sono tutte quelle da includere, o sono solo le prime? (I connettori FDI sono 13.)
4. Linee: come si raggruppano le 4 macchine in linee e reparti?
5. CCP: quali sono, su quale macchina, con che frequenza di misura e con quali limiti?
   I limiti sono registrati in NetPro o su carta / in Excel?
6. Turni: orari, e se ci sono turni diversi nel weekend.
7. Il modulo NetPro "Qualità/HACCP" (se presente) registra deviazioni e azioni correttive?
8. Avete report nativi NetPro di OEE e CCP da usare come riferimento (in Fase 4)?

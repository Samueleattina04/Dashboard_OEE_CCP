# Fase 1 — Scoperta del database MES (sola lettura)

Script per mappare il database **Netpro** (Qualitas) senza scrivere nulla e senza caricare il MES.

## Cosa fanno

| Script | Cosa legge | Impatto sul MES |
|---|---|---|
| `00_info_server` | versione SQL Server, utente, ora/fuso | nullo (metadati) |
| `01_permessi_utente` | permessi e ruoli effettivi di `lettore` | nullo (metadati) |
| `02_tabelle_viste` | tabelle/viste con righe stimate | nullo (metadati, nessun COUNT) |
| `03_colonne` | colonne, tipi, descrizioni | nullo (metadati) |
| `04_indici` | indici e colonne chiave | nullo (metadati) |
| `05_chiavi_esterne` | relazioni dichiarate | nullo (metadati) |
| `06_viste_routine` | testo di viste/procedure/trigger | nullo (metadati, nulla viene eseguito) |
| `07_ricerca_parole_chiave` | colonne con nomi OEE/CCP | nullo (metadati) |
| `08_fprocessdata_estremi` | primo/ultimo valore indice di FProcessdata | 2 letture puntuali sull'indice |
| `09_fprocessdata_campione_recente` | ultime 200 righe di FProcessdata | TOP 200 sull'indice |
| `10_fprocessdata_macchine_recenti` | macchine (FObject) nelle ultime 20.000 righe | TOP 20.000 sull'indice |

Le query 08–10 usano `READ UNCOMMITTED` per non prendere lock che rallentino le scritture delle app FDI
e `LOCK_TIMEOUT 5000` per arrendersi invece di attendere.

## Come eseguire

1. Copiare la cartella `discovery` su un PC/server che raggiunge `192.168.3.205` (es. `192.168.3.206`).
2. Aprire PowerShell nella cartella ed eseguire:
   ```powershell
   powershell -ExecutionPolicy Bypass -File .\esegui_scoperta.ps1
   ```
3. Inserire la password di `lettore` quando richiesta (non viene salvata).
4. Controllare `output\` e **rimuovere eventuali dati sensibili** (nomi operatori, password in chiaro
   nelle definizioni delle viste) prima di inviarla.

La cartella `output/` è esclusa da git.

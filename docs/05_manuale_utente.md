# Manuale utente — Dashboard OEE e CCP (bozza)

Versione bozza del 06/11/2026, da rifinire dopo le prime prove. Indirizzo: **http://192.168.3.206:8010**
(diventerà un indirizzo HTTPS quando l'app sarà pubblicata in produzione).

---

## Pagina 1 — Qualità

### Accesso
- Ricevi un'**email di invito** con un link personale (valido 7 giorni): aprilo, inserisci nome e password (almeno 12 caratteri, con maiuscole, minuscole, numeri e simboli).
- Accedi poi con **la tua email e la password**.
- **Password dimenticata?** Usa il link sotto il pulsante *Accedi*: l'amministratore riceve la richiesta e ti manda un link per sceglierne una nuova (valido 24 ore).
- In alto a destra il pulsante **Tema** cambia tra automatico, chiaro e scuro.

### Cosa puoi fare
| Menu | A cosa serve |
|---|---|
| **Panoramica** | Ore di funzionamento di forno e selezionatrice nel periodo scelto, con il grafico giorno per giorno |
| **Stato macchine** | Stato attuale di ogni macchina (aggiornato ogni 15 minuti) |
| **Storico e confronti** | Un periodo contro un altro (es. questa settimana contro la precedente) |
| **Dettaglio macchina** (clic sul nome) | Timeline della giornata e **valori originali registrati dal MES** |
| **Esporta Excel / CSV** | Tabelle esportabili; ogni export viene registrato (chi, cosa, quando) |

### Il dato registrato (tracciabilità)
- Nel **Dettaglio macchina**, la sezione *Campioni del giorno* mostra i valori **esattamente come registrati** nel MES (es. temperatura di tostatura `48,9`), con orario originale e orario di Roma. Nessun valore è arrotondato o ricostruito.
- Fonte: `NETPRO.dbo.FPROCESSDATA`, un campione ogni 15 minuti.
- La dashboard **legge soltanto** il MES: non può modificarlo.

### Come leggere i colori
- **In funzione** (blu), **Ferma** (grigio), **Allarme** (viola), **Avvio/setup** (giallo).
- Solo forno: **Riscaldamento** (arancio) e **Raffreddamento** (verde), cioè la temperatura di tostatura tra 70 e 100 °C.
- **Senza dato** (tratteggio chiaro): il MES non ha registrato un valore valido. **Non** significa macchina ferma.
- **Non classificato** (tratteggio scuro): valore registrato ma dal significato non ancora noto (es. stato `End3` della selezionatrice).

### CCP
Le viste CCP (carte di controllo, limiti, deviazioni, report PDF per audit) **non sono ancora attive**: verranno definite con la Qualità dopo i primi dati di funzionamento.

---

## Pagina 2 — Direzione

### Accesso
Come per la Qualità: invito via email, poi accesso con email e password; link *Password dimenticata?* in caso di necessità.

### Le domande a cui risponde
| Domanda | Dove guardare |
|---|---|
| Quanto hanno lavorato le macchine? | **Panoramica** → *Ore in funzione* (totale e media al giorno) |
| Stanno lavorando adesso? | **Stato macchine** |
| Va meglio o peggio del mese scorso? | **Storico e confronti** → periodo A contro periodo B, con differenze |
| Cosa è successo in una giornata? | **Dettaglio macchina** → timeline della giornata |

### Gli indicatori (passa il mouse sulla **i** per la formula)
| Indicatore | Significato |
|---|---|
| **Ore in funzione** | Tempo in cui la macchina lavora secondo i dati del MES (risoluzione ±15 min) |
| **Utilizzo sul tempo coperto** | Ore in funzione divise per il tempo in cui il dato è disponibile |
| **Copertura dati** | Quota di tempo con dati validi: se è bassa, gli altri indicatori vanno letti con cautela |
| **Disponibilità** | Ore in funzione **nel turno** divise per le ore **pianificate** (turni − pause − fermi programmati) |
| **Prestazione** (selezionatrice) | Kg lavorati divisi per quelli teorici alla capacità nominale |
| **OEE** | Disponibilità × Prestazione × Qualità. Oggi **n.d.**: la definizione di Qualità è ancora da decidere |

**"n.d."** significa *non disponibile*. Sotto il valore è indicato il motivo (es. turni non configurati). Un valore mancante non viene mai sostituito con 0% o 100%.

Le percentuali di un periodo sono **pesate** (prima si sommano le ore, poi si calcola il rapporto): un giorno con 2 ore pianificate pesa meno di uno con 8.

### Limiti da conoscere
- I dati arrivano **ogni 15 minuti**: durate e fermi brevi hanno un'incertezza fino a 15 minuti.
- **Forno**: non trasmette uno stato di marcia, quindi "in funzione" significa *temperatura di tostatura ≥ 100 °C*.
- **Selezionatrice FX10130**: ha lunghi periodi senza dati (es. febbraio–maggio 2026), visibili come *Senza dato*; i kg sono indicativi, non fiscali.

### Configurazione (solo Admin)
Turni e pause, festivi e fermi programmati, capacità in kg/h, target, soglie del forno e stati della selezionatrice si impostano dal menu **Configurazione**. Ogni modifica è registrata in **Storico modifiche** e ricalcola automaticamente i giorni interessati.

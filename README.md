# Dashboard OEE e CCP — Antichi Sapori dell'Etna

Web app interna, in **sola lettura** sul MES Qualitas NetPro, per la consultazione di OEE (Direzione)
e, in seguito, dei CCP HACCP (Qualità).

## Stato del progetto

- [x] Fase 1 — Scoperta database: `docs/01_mappa_db.md` (approvata il 05/11/2026)
- [x] Fase 2 — Definizioni KPI e modello dati: `docs/02_definizioni_kpi.md` (approvata il 05/11/2026)
- [ ] Fase 3 — Sviluppo (in corso): ore in funzione, Disponibilità e Prestazione per FX10130 e FORNOKING500; CCP dopo i primi dati OEE
- [ ] Fase 4 — Verifica e riconciliazione

## Struttura

| Cartella | Contenuto |
|---|---|
| `webapp/` | Applicazione Laravel 13 (PHP 8.3, MySQL 8.4 in produzione, SQLite per la prova) |
| `docs/` | Mappa DB, definizioni KPI, guida di installazione |
| `discovery/` | Script SQL di sola lettura usati per la scoperta del database MES |

## Installazione

Vedi **`docs/03_installazione.md`** (prima prova con `php artisan serve`, poi produzione con IIS + MySQL).

## Sviluppo e test

```bash
cd webapp && composer install && php artisan test
```

I test includono i casi calcolati a mano di `docs/02_definizioni_kpi.md` §9 e la matrice dei permessi per ruolo.

<?php

namespace App\Http\Controllers\Admin;

use App\Domain\StatoMacchina;
use App\Http\Controllers\Controller;
use App\Models\AuditModifica;
use App\Models\CalendarioTurni;
use App\Models\CapacitaNominale;
use App\Models\EccezioneCalendario;
use App\Models\Macchina;
use App\Models\RegolaStato;
use App\Models\SyncMes;
use App\Models\Turno;
use App\Services\RicalcoloKpi;
use App\Support\Audit;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Admin e SuperAdmin: dati che il MES non ha (turni, capacità, soglie e regole di stato, target).
 * Ogni modifica è storicizzata (audit_modifiche) e le regole/capacità sono versionate con data di validità.
 */
class ConfigurazioneController extends Controller
{
    public function index()
    {
        $macchine = Macchina::orderBy('codice')->get();
        foreach ($macchine as $m) {
            $m->regola_attuale = $m->regole()->whereNull('valido_al')->orderByDesc('valido_dal')->first();
            $m->capacita_attuale = $m->capacitaValidaIl(now()->format('Y-m-d'));
            $m->target_attuale = DB::table('target_oee')->where('macchina_id', $m->id)->whereNull('valido_al')->orderByDesc('valido_dal')->first();
        }

        return view('admin.configurazione', [
            'macchine' => $macchine,
            'calendari' => CalendarioTurni::with(['turni' => fn ($q) => $q->orderBy('giorno_settimana')->orderBy('ora_inizio')])
                ->with(['eccezioni' => fn ($q) => $q->orderByDesc('data')->limit(50)])->get(),
            'sync' => SyncMes::with([])->get()->keyBy('macchina_id'),
            'stati' => StatoMacchina::cases(),
        ]);
    }

    public function audit()
    {
        return view('admin.audit', [
            'voci' => AuditModifica::orderByDesc('id')->paginate(50),
            'utenti' => DB::table('users')->pluck('email', 'id'),
        ]);
    }

    /** Nuova versione della regola di stato (soglie forno o mappa valori) valida da una data. */
    public function aggiornaRegola(Request $request, Macchina $macchina, RicalcoloKpi $ricalcolo)
    {
        $attuale = $macchina->regole()->whereNull('valido_al')->orderByDesc('valido_dal')->firstOrFail();
        $comune = $request->validate([
            'valido_dal' => ['required', 'date_format:Y-m-d'],
            'g_max_minuti' => ['required', 'integer', 'min:15', 'max:120'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $parametri = $attuale->parametri;
        $parametri['g_max_minuti'] = (int) $comune['g_max_minuti'];

        if ($attuale->tipo === 'soglie_temperatura') {
            $dati = $request->validate([
                'soglia_marcia' => ['required', 'numeric', 'gt:soglia_transitorio'],
                'soglia_transitorio' => ['required', 'numeric'],
            ]);
            $parametri['soglia_marcia'] = (string) $dati['soglia_marcia'];
            $parametri['soglia_transitorio'] = (string) $dati['soglia_transitorio'];
        } elseif ($attuale->tipo === 'soglia_colonne') {
            $dati = $request->validate(['soglia' => ['required', 'numeric', 'min:0']]);
            $parametri['soglia'] = (string) $dati['soglia'];
        } else {
            $valori = array_column(StatoMacchina::cases(), 'value');
            $dati = $request->validate([
                'mappa' => ['required', 'array'],
                'mappa.*' => ['required', Rule::in($valori)],
                'nuovo_valore' => ['nullable', 'string', 'max:60'],
                'nuovo_stato' => ['nullable', Rule::in($valori)],
            ]);
            $parametri['mappa'] = $dati['mappa'];
            if (! empty($dati['nuovo_valore']) && ! empty($dati['nuovo_stato'])) {
                $parametri['mappa'][trim($dati['nuovo_valore'])] = $dati['nuovo_stato'];
            }
        }

        $dal = $this->inizioGiornoUtc($comune['valido_dal']);
        if ($dal <= $attuale->valido_dal->format('Y-m-d H:i:s') && $attuale->valido_dal->format('Y') !== '2000') {
            return back()->withErrors(['valido_dal' => 'La nuova regola deve partire dopo l\'inizio di quella attuale ('
                .$attuale->valido_dal->setTimezone(config('dashboard.timezone_locale'))->format('d/m/Y').').']);
        }
        DB::transaction(function () use ($macchina, $attuale, $parametri, $comune, $dal) {
            $prima = $attuale->toArray();
            $attuale->update(['valido_al' => $dal]);
            Audit::registra('modifica', $attuale, $prima);
            $nuova = RegolaStato::create([
                'macchina_id' => $macchina->id, 'tipo' => $attuale->tipo, 'parametri' => $parametri,
                'valido_dal' => $dal, 'note' => $comune['note'] ?? null, 'creato_da' => Auth::id(),
            ]);
            Audit::registra('creazione', $nuova);
        });

        $n = $this->ricalcolaDa($ricalcolo, $macchina, $comune['valido_dal']);

        return back()->with('ok', "Regola di {$macchina->codice} aggiornata dal {$comune['valido_dal']}: ricalcolati {$n} giorni.");
    }

    /** Attiva/disattiva una macchina (le macchine non attive non vengono importate né mostrate). */
    public function impostaAttiva(Request $request, Macchina $macchina)
    {
        $dati = $request->validate(['attiva' => ['required', 'boolean']]);
        $prima = $macchina->only(['attiva']);
        $macchina->update(['attiva' => (bool) $dati['attiva']]);
        Audit::registra('modifica', $macchina, $prima);

        return back()->with('ok', "{$macchina->codice} ".($macchina->attiva
            ? 'attivata. Per caricare lo storico: php artisan mes:storico '.$macchina->codice.' --da=AAAA-MM-GG'
            : 'disattivata.'));
    }

    public function aggiungiCapacita(Request $request, Macchina $macchina, RicalcoloKpi $ricalcolo)
    {
        $dati = $request->validate([
            'kg_ora' => ['required', 'numeric', 'gt:0'],
            'valido_dal' => ['required', 'date_format:Y-m-d'],
        ]);
        DB::transaction(function () use ($macchina, $dati) {
            $precedente = $macchina->capacita()->whereNull('valido_al')->where('valido_dal', '<', $dati['valido_dal'])->orderByDesc('valido_dal')->first();
            if ($precedente) {
                $prima = $precedente->toArray();
                $precedente->update(['valido_al' => (new DateTimeImmutable($dati['valido_dal']))->modify('-1 day')->format('Y-m-d')]);
                Audit::registra('modifica', $precedente, $prima);
            }
            $nuova = CapacitaNominale::create(['macchina_id' => $macchina->id] + $dati);
            Audit::registra('creazione', $nuova);
        });
        $n = $this->ricalcolaDa($ricalcolo, $macchina, $dati['valido_dal']);

        return back()->with('ok', "Capacità di {$macchina->codice} registrata: ricalcolati {$n} giorni.");
    }

    public function aggiungiTarget(Request $request, Macchina $macchina)
    {
        $dati = $request->validate([
            'oee' => ['nullable', 'numeric', 'between:0,100'],
            'disponibilita' => ['nullable', 'numeric', 'between:0,100'],
            'valido_dal' => ['required', 'date_format:Y-m-d'],
        ]);
        DB::table('target_oee')->where('macchina_id', $macchina->id)->whereNull('valido_al')
            ->update(['valido_al' => $dati['valido_dal'], 'updated_at' => now()]);
        $id = DB::table('target_oee')->insertGetId([
            'macchina_id' => $macchina->id,
            'oee' => isset($dati['oee']) ? $dati['oee'] / 100 : null,
            'disponibilita' => isset($dati['disponibilita']) ? $dati['disponibilita'] / 100 : null,
            'valido_dal' => $dati['valido_dal'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('audit_modifiche')->insert(['user_id' => Auth::id(), 'tabella' => 'target_oee', 'record_id' => $id,
            'azione' => 'creazione', 'valore_nuovo' => json_encode($dati), 'creato_il' => now()]);

        return back()->with('ok', "Target di {$macchina->codice} registrato.");
    }

    public function creaCalendario(Request $request)
    {
        $dati = $request->validate(['nome' => ['required', 'string', 'max:80', 'unique:calendari_turni,nome']]);
        $cal = CalendarioTurni::create($dati);
        Audit::registra('creazione', $cal);

        return back()->with('ok', "Calendario «{$cal->nome}» creato.");
    }

    public function aggiungiTurno(Request $request, CalendarioTurni $calendario, RicalcoloKpi $ricalcolo)
    {
        $dati = $request->validate([
            'tipo' => ['required', Rule::in(['turno', 'pausa'])],
            'nome' => ['required', 'string', 'max:60'],
            'giorni' => ['required', 'array', 'min:1'],
            'giorni.*' => ['integer', 'between:1,7'],
            'ora_inizio' => ['required', 'date_format:H:i'],
            'ora_fine' => ['required', 'date_format:H:i', 'different:ora_inizio'],
        ]);
        foreach ($dati['giorni'] as $g) {
            $t = Turno::create(['calendario_id' => $calendario->id, 'tipo' => $dati['tipo'], 'nome' => $dati['nome'],
                'giorno_settimana' => $g, 'ora_inizio' => $dati['ora_inizio'], 'ora_fine' => $dati['ora_fine']]);
            Audit::registra('creazione', $t);
        }
        $n = $this->ricalcolaCalendario($ricalcolo, $calendario);

        return back()->with('ok', "Aggiunto «{$dati['nome']}»: ricalcolati {$n} giorni-macchina.");
    }

    public function eliminaTurno(Turno $turno, RicalcoloKpi $ricalcolo)
    {
        $prima = $turno->toArray();
        $calendario = CalendarioTurni::find($turno->calendario_id);
        $turno->delete();
        Audit::registra('eliminazione', $turno, $prima);
        $n = $this->ricalcolaCalendario($ricalcolo, $calendario);

        return back()->with('ok', "Turno eliminato: ricalcolati {$n} giorni-macchina.");
    }

    public function aggiungiEccezione(Request $request, CalendarioTurni $calendario, RicalcoloKpi $ricalcolo)
    {
        $dati = $request->validate([
            'data' => ['required', 'date_format:Y-m-d'],
            'tipo' => ['required', Rule::in(['festivo', 'fermo_programmato', 'assenza_ordini'])],
            'macchina_id' => ['nullable', 'exists:macchine,id'],
            'ora_inizio' => ['nullable', 'date_format:H:i', 'required_with:ora_fine'],
            'ora_fine' => ['nullable', 'date_format:H:i', 'required_with:ora_inizio'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $e = EccezioneCalendario::create(['calendario_id' => $calendario->id] + $dati);
        Audit::registra('creazione', $e);
        foreach (Macchina::where('calendario_id', $calendario->id)->get() as $m) {
            $ricalcolo->ricalcola($m, [$dati['data']]);
        }

        return back()->with('ok', 'Eccezione registrata.');
    }

    public function eliminaEccezione(EccezioneCalendario $eccezione, RicalcoloKpi $ricalcolo)
    {
        $prima = $eccezione->toArray();
        $eccezione->delete();
        Audit::registra('eliminazione', $eccezione, $prima);
        foreach (Macchina::where('calendario_id', $eccezione->calendario_id)->get() as $m) {
            $ricalcolo->ricalcola($m, [(new DateTimeImmutable($eccezione->data))->format('Y-m-d')]);
        }

        return back()->with('ok', 'Eccezione eliminata.');
    }

    public function assegnaCalendario(Request $request, Macchina $macchina, RicalcoloKpi $ricalcolo)
    {
        $dati = $request->validate(['calendario_id' => ['nullable', 'exists:calendari_turni,id']]);
        $prima = $macchina->only(['calendario_id']);
        $macchina->update(['calendario_id' => $dati['calendario_id'] ?? null]);
        Audit::registra('modifica', $macchina, $prima);
        $n = $this->ricalcolaDa($ricalcolo, $macchina, now()->subDays(89)->format('Y-m-d'));

        return back()->with('ok', "Calendario assegnato a {$macchina->codice}: ricalcolati {$n} giorni.");
    }

    /** Importazione immediata dal MES (stessa query del job ogni 15 minuti). */
    public function importaOra()
    {
        try {
            Artisan::call('mes:importa');
            $esito = trim(Artisan::output());
        } catch (Throwable $e) {
            $esito = 'Errore: '.$e->getMessage();
        }

        return back()->with('ok', 'Importazione eseguita. '.$esito);
    }

    public function ricalcola(Request $request, RicalcoloKpi $ricalcolo)
    {
        $dati = $request->validate(['da' => ['required', 'date_format:Y-m-d'], 'macchina_id' => ['required', 'exists:macchine,id']]);
        $n = $this->ricalcolaDa($ricalcolo, Macchina::find($dati['macchina_id']), $dati['da']);

        return back()->with('ok', "Ricalcolati {$n} giorni.");
    }

    // ------------------------------------------------------------------

    private function inizioGiornoUtc(string $giorno): string
    {
        return (new DateTimeImmutable($giorno.' 00:00:00', new DateTimeZone(config('dashboard.timezone_locale'))))
            ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function ricalcolaDa(RicalcoloKpi $ricalcolo, Macchina $macchina, string $da): int
    {
        set_time_limit(600);
        $tz = new DateTimeZone(config('dashboard.timezone_locale'));
        $oggi = new DateTimeImmutable('today', $tz);
        $giorni = [];
        for ($g = new DateTimeImmutable($da, $tz); $g <= $oggi; $g = $g->modify('+1 day')) {
            $giorni[] = $g->format('Y-m-d');
        }
        $ricalcolo->ricalcola($macchina, $giorni);

        return count($giorni);
    }

    /** I turni non sono versionati: una modifica si applica agli ultimi 90 giorni (ricalcolo storico completo dal pannello). */
    private function ricalcolaCalendario(RicalcoloKpi $ricalcolo, ?CalendarioTurni $calendario): int
    {
        $n = 0;
        foreach (Macchina::where('calendario_id', $calendario?->id)->get() as $m) {
            $n += $this->ricalcolaDa($ricalcolo, $m, now()->subDays(89)->format('Y-m-d'));
        }

        return $n;
    }
}

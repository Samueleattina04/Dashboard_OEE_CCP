{{-- Filtri globali riflessi nell'URL. Parametri: $filtri, $macchine (opzionale), $extra (campi nascosti) --}}
<form method="get" class="filtri" aria-label="Filtri">
    <label>Periodo
        <select name="periodo" onchange="document.getElementById('date-pers').hidden = this.value !== 'personalizzato'">
            @foreach (\App\Support\Filtri::PRESET as $k => $v)
                <option value="{{ $k }}" @selected($filtri->periodo === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </label>
    <span id="date-pers" style="display:contents" @if ($filtri->periodo !== 'personalizzato') hidden @endif>
        <label>Dal<input type="date" name="da" value="{{ $filtri->da }}"></label>
        <label>Al<input type="date" name="a" value="{{ $filtri->a }}"></label>
    </span>
    @isset($macchine)
        <label>Macchina
            <select name="macchina">
                <option value="">Tutte</option>
                @foreach ($macchine as $m)
                    <option value="{{ $m->codice }}" @selected($filtri->macchina === $m->codice)>{{ $m->codice }}</option>
                @endforeach
            </select>
        </label>
    @endisset
    @foreach (($extra ?? []) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <button>Applica</button>
    <span class="muto piccolo">{{ $filtri->descrizione() }}</span>
</form>

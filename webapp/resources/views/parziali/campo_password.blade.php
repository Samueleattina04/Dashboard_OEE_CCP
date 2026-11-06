{{-- Campo password con pulsante "mostra/nascondi". Parametri: $nome, $etichetta, $autocomplete, $minlength (opz.) --}}
<label>{{ $etichetta }}
    <span class="campo-password">
        <input type="password" name="{{ $nome }}" required autocomplete="{{ $autocomplete }}" @isset($minlength) minlength="{{ $minlength }}" @endisset>
        <button type="button" class="mostra-password" data-mostra-password aria-label="Mostra password" title="Mostra password" aria-pressed="false">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path class="occhio-aperto" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle class="occhio-aperto" cx="12" cy="12" r="3"/>
                <path class="occhio-chiuso" d="M17.9 17.9A10.9 10.9 0 0 1 12 19c-7 0-11-7-11-7a20 20 0 0 1 5.1-5.9M9.9 5.2A10 10 0 0 1 12 5c7 0 11 7 11 7a20 20 0 0 1-2.2 3.2M1 1l22 22"/>
            </svg>
        </button>
    </span>
</label>

<div class="legenda" aria-label="Legenda stati">
    @foreach (($stati ?? \App\Domain\StatoMacchina::ordineVisualizzazione()) as $s)
        <span><i class="campione-colore st-{{ $s->value }}"></i>{{ $s->etichetta() }}</span>
    @endforeach
</div>

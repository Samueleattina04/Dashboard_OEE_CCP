<?php

namespace App\Domain\Classificatori;

use InvalidArgumentException;

final class FabbricaClassificatori
{
    public static function crea(string $tipo, array $parametri): ClassificatoreStato
    {
        return match ($tipo) {
            'mappa_valori' => ClassificatoreMappaValori::daParametri($parametri),
            'soglie_temperatura' => ClassificatoreSoglieTemperatura::daParametri($parametri),
            default => throw new InvalidArgumentException("Tipo di regola sconosciuto: {$tipo}"),
        };
    }
}

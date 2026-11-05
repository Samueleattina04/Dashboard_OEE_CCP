<?php

namespace App\Mes;

interface SorgenteCampioni
{
    /**
     * Righe HIST di FPROCESSDATA successive a $dopoFtimestamp (escluso), ordinate per FTIMESTAMP, FROW.
     *
     * @param  string[]  $colonne  colonne valori da leggere (FVALUEnn / FNVALUEnn)
     * @return array<int, array<string, mixed>> chiavi: FTIMESTAMP (string locale), FROW, FBATCH, FCHANNEL e le colonne richieste
     */
    public function campioniDopo(string $macchina, array $colonne, ?string $dopoFtimestamp, ?string $finoA, int $limite): array;
}

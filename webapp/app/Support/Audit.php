<?php

namespace App\Support;

use App\Models\AuditModifica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Storicizzazione delle modifiche di configurazione: chi, quando, valore precedente e nuovo.
 */
final class Audit
{
    public static function registra(string $azione, Model $record, ?array $prima = null): void
    {
        AuditModifica::create([
            'user_id' => Auth::id(),
            'tabella' => $record->getTable(),
            'record_id' => $record->getKey(),
            'azione' => $azione,
            'valore_precedente' => $prima,
            'valore_nuovo' => $azione === 'eliminazione' ? null : $record->fresh()?->toArray(),
        ]);
    }
}

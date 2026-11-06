<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LogAccessi
{
    public static function registra(Request $request, string $evento, ?int $userId, ?string $email): void
    {
        DB::table('log_accessi')->insert([
            'user_id' => $userId,
            'email' => $email ? mb_substr($email, 0, 255) : null,
            'evento' => $evento,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'creato_il' => now(),
        ]);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Brings stored addresses in line with `User::normalizeEmail()`: lowercased
 * and trimmed, the spelling login, registration and invitations now look
 * them up by. Before this, Postgres compared them exactly, so `Bob@x.com`
 * could not sign in as `bob@x.com`.
 *
 * A user whose lowercased address is already held by another account is left
 * as it is and logged: two accounts for one mailbox need a person to decide
 * which survives. `pending_email` is left alone too: an email-change link
 * already sent is signed over the address as it was stored, and confirming it
 * lowercases the address anyway.
 *
 * Irreversible by design: the original capitalization is not kept anywhere.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->select('id', 'email')
            ->whereRaw('email <> lower(trim(email))')
            ->lazyById()
            ->each(function (object $user): void {
                $normalized = Str::lower(trim($user->email));

                if (DB::table('users')->where('email', $normalized)->exists()) {
                    Log::warning('Email not lowercased: another account already uses that address.', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ]);

                    return;
                }

                DB::table('users')->where('id', $user->id)->update(['email' => $normalized]);
            });

        DB::table('workspace_invitations')
            ->whereRaw('email <> lower(trim(email))')
            ->update(['email' => DB::raw('lower(trim(email))')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The original capitalization was not kept, so there is nothing to restore.
    }
};

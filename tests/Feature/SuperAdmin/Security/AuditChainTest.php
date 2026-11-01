<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Notifications\SuperAdmin\SuperAdminAlertNotification;
use App\Services\SuperAdmin\Audit\AuditChainVerifier;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\SuperAdmin\Auth\SuperAdminTokenService;
use Database\Factories\SuperAdmin\SuperAdminFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Writes `$count` entries through the logger, with values that are awkward to
 * hash: floats that look whole, unicode, nested and empty arrays, nulls.
 */
function chainOf(int $count = 5): void
{
    $audit = app(SuperAdminAuditLogger::class);

    foreach (range(1, $count) as $i) {
        $audit->record(null, "test.entry.{$i}", null, ['price' => 3.0, 'nested' => ['b' => 2, 'a' => [1, 2]], 'empty' => []], ['name' => "Zoë — {$i}", 'ratio' => 2.5, 'gone' => null], "Reason number {$i}", 'cli:tester');
    }
}

it('chains every entry to the one before it, from 1 with no gaps', function () {
    chainOf(4);

    $entries = SuperAdminAuditLog::query()->orderBy('sequence')->get();

    expect($entries->pluck('sequence')->all())->toBe([1, 2, 3, 4])
        ->and($entries[0]->previous_hash)->toBeNull()
        ->and($entries[1]->previous_hash)->toBe($entries[0]->hash)
        ->and($entries[3]->previous_hash)->toBe($entries[2]->hash)
        ->and($entries->pluck('hash')->unique())->toHaveCount(4)
        ->and($entries[0]->hash)->toHaveLength(64);
});

it('finds an untouched log intact, after a round trip through the database', function () {
    chainOf(5);

    $result = app(AuditChainVerifier::class)->verify();

    expect($result)->toMatchArray(['intact' => true, 'checked' => 5, 'head_sequence' => 5, 'break' => null])
        ->and($result['head_hash'])->toBe(SuperAdminAuditLog::query()->orderByDesc('sequence')->value('hash'));
});

it('treats an empty log as intact', function () {
    expect(app(AuditChainVerifier::class)->verify())->toMatchArray(['intact' => true, 'checked' => 0, 'head_sequence' => null, 'head_hash' => null]);
});

it('catches an entry that was changed', function (string $column, mixed $value) {
    chainOf(5);
    $target = SuperAdminAuditLog::query()->where('sequence', 3)->firstOrFail();

    DB::table('super_admin_audit_logs')->where('id', $target->id)->update([$column => $value]);

    $result = app(AuditChainVerifier::class)->verify();

    expect($result['intact'])->toBeFalse()
        ->and($result['break'])->toMatchArray(['sequence' => 3, 'reason' => 'hash_mismatch'])
        ->and($result['checked'])->toBe(2);
})->with([
    'the action' => ['action', 'something.else'],
    'the reason' => ['reason', 'A different reason'],
    'who did it' => ['actor_label', 'cli:somebody-else'],
    'what it was about' => ['subject_id', '01a169d4-4d83-7380-9987-4bd93a3f1481'],
    'the before values' => ['before', '{"price":99}'],
    'the after values' => ['after', '{"name":"x"}'],
    'the address' => ['ip_address', '203.0.113.9'],
    'the time' => ['created_at', '2020-01-01 00:00:00'],
]);

it('catches an entry that was removed, wherever it was', function (int $removed, string $reason) {
    chainOf(5);

    SuperAdminAuditLog::query()->where('sequence', $removed)->delete();

    $result = app(AuditChainVerifier::class)->verify();

    expect($result['intact'])->toBeFalse()
        ->and($result['break']['reason'])->toBe($reason);
})->with([
    'the first' => [1, 'sequence_gap'],
    'one in the middle' => [3, 'sequence_gap'],
]);

it('catches an entry slipped in without being chained', function () {
    chainOf(3);

    SuperAdminAuditLog::query()->create(['action' => 'forged.entry', 'actor_label' => 'cli:attacker']);

    $result = app(AuditChainVerifier::class)->verify();

    expect($result['intact'])->toBeFalse()
        ->and($result['break']['reason'])->toBe('unchained_entry');
});

it('catches an entry whose link to the one before was cut', function () {
    chainOf(4);

    DB::table('super_admin_audit_logs')->where('sequence', 3)->update(['previous_hash' => str_repeat('a', 64)]);

    expect(app(AuditChainVerifier::class)->verify()['break'])->toMatchArray(['sequence' => 3, 'reason' => 'previous_hash_mismatch']);
});

it('cannot be repaired by someone who recomputes the hashes without the application key', function () {
    chainOf(3);
    $second = SuperAdminAuditLog::query()->where('sequence', 2)->firstOrFail();

    // The tamperer edits the entry and recomputes its hash and the next one's with plain SHA-256.
    DB::table('super_admin_audit_logs')->where('id', $second->id)->update(['reason' => 'edited', 'hash' => hash('sha256', 'edited')]);

    expect(app(AuditChainVerifier::class)->verify()['intact'])->toBeFalse();

    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    chainOf(1);

    // A different application key cannot verify what the old one wrote.
    expect(app(AuditChainVerifier::class)->verify()['intact'])->toBeFalse();
});

it('catches entries cut off the end when given the head that was recorded', function () {
    chainOf(4);
    $head = app(AuditChainVerifier::class)->verify()['head_hash'];

    expect(app(AuditChainVerifier::class)->verify($head)['intact'])->toBeTrue();

    SuperAdminAuditLog::query()->where('sequence', 4)->delete();

    expect(app(AuditChainVerifier::class)->verify()['intact'])->toBeTrue();

    $result = app(AuditChainVerifier::class)->verify($head);

    expect($result['intact'])->toBeFalse()->and($result['break']['reason'])->toBe('head_mismatch');
});

it('numbers entries written inside a surrounding transaction in order', function () {
    DB::transaction(function () {
        chainOf(3);
    });

    chainOf(2);

    expect(SuperAdminAuditLog::query()->orderBy('sequence')->pluck('sequence')->all())->toBe([1, 2, 3, 4, 5])
        ->and(app(AuditChainVerifier::class)->verify()['intact'])->toBeTrue();
});

it('writes nothing to the chain for an entry whose transaction was rolled back', function () {
    chainOf(2);

    try {
        DB::transaction(function () {
            chainOf(1);

            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
    }

    chainOf(1);

    expect(SuperAdminAuditLog::query()->orderBy('sequence')->pluck('sequence')->all())->toBe([1, 2, 3])
        ->and(app(AuditChainVerifier::class)->verify()['intact'])->toBeTrue();
});

it('covers the entries the API itself writes', function () {
    $owner = superAdmin();
    $target = superAdmin(SuperAdminRole::Support);

    $this->withHeaders(superAdminHeaders($owner))->postJson(saUrl("accounts/{$target->id}/role"), stepUp($owner, ['role' => 'finance']))->assertOk();
    $this->withHeaders(superAdminHeaders($owner))->postJson(saUrl("accounts/{$target->id}/disable"), stepUp($owner))->assertOk();

    expect(SuperAdminAuditLog::query()->whereNull('sequence')->count())->toBe(0)
        ->and(app(AuditChainVerifier::class)->verify())->toMatchArray(['intact' => true, 'checked' => 2]);
});

it('exposes the check, to anyone who can read the audit log', function (SuperAdminRole $role) {
    chainOf(3);

    $this->withHeaders(superAdminHeaders(superAdmin($role)))->getJson(saUrl('audit-log/integrity'))
        ->assertOk()
        ->assertJsonPath('data.intact', true)
        ->assertJsonPath('data.checked', 3)
        ->assertJsonPath('data.head_sequence', 3);
})->with(array_map(fn (SuperAdminRole $role) => [$role], SuperAdminRole::cases()));

it('reports a broken log through the API', function () {
    chainOf(3);
    SuperAdminAuditLog::query()->where('sequence', 2)->delete();

    $this->withHeaders(superAdminHeaders(superAdmin()))->getJson(saUrl('audit-log/integrity'))
        ->assertOk()
        ->assertJsonPath('data.intact', false)
        ->assertJsonPath('data.break.reason', 'sequence_gap');
});

it('takes a recorded head to check against', function () {
    chainOf(2);
    $headers = superAdminHeaders(superAdmin());

    $this->withHeaders($headers)->getJson(saUrl('audit-log/integrity?expected_head='.str_repeat('0', 64)))
        ->assertOk()->assertJsonPath('data.break.reason', 'head_mismatch');

    $this->withHeaders($headers)->getJson(saUrl('audit-log/integrity?expected_head=short'))->assertUnprocessable();
});

it('lists the sequence and hash on each entry', function () {
    chainOf(1);

    $this->withHeaders(superAdminHeaders(superAdmin()))->getJson(saUrl('audit-log'))
        ->assertOk()
        ->assertJsonPath('data.0.sequence', 1)
        ->assertJsonPath('data.0.hash', SuperAdminAuditLog::query()->value('hash'));
});

it('verifies from the command line, and says where it broke', function () {
    chainOf(3);

    $this->artisan('super-admin:audit-verify')->expectsOutputToContain('Checked 3 entries')->expectsOutputToContain('intact')->assertSuccessful();

    DB::table('super_admin_audit_logs')->where('sequence', 2)->update(['reason' => 'edited']);

    $this->artisan('super-admin:audit-verify')->expectsOutputToContain('Entry 2 was changed after it was written')->assertFailed();
});

it('raises an alert when the check fails, and only then', function () {
    Notification::fake();
    config(['super_admin_alerts.recipients.mail' => ['security@example.test']]);
    chainOf(3);

    $this->artisan('super-admin:audit-verify')->assertSuccessful();
    Notification::assertNothingSent();

    SuperAdminAuditLog::query()->where('sequence', 2)->delete();
    $this->artisan('super-admin:audit-verify')->assertFailed();

    Notification::assertSentOnDemand(SuperAdminAlertNotification::class, fn (SuperAdminAlertNotification $alert): bool => $alert->event === 'audit_tampering');
});

it('checks the recorded head from the command line', function () {
    chainOf(3);
    $head = app(AuditChainVerifier::class)->verify()['head_hash'];

    $this->artisan('super-admin:audit-verify', ['--expect-hash' => $head])->assertSuccessful();
    $this->artisan('super-admin:audit-verify', ['--expect-hash' => str_repeat('f', 64)])->assertFailed();
});

it('does not run while the master switch is off', function () {
    config(['super_admin.enabled' => false]);

    $this->artisan('super-admin:audit-verify')->assertFailed();
    $this->artisan('super-admin:lockdown')->assertFailed();
});

it('ends every session of every super admin in a lockdown, and says so in the log', function () {
    Notification::fake();
    config(['super_admin_alerts.recipients.mail' => ['security@example.test']]);

    $owner = superAdmin();
    $other = superAdmin(SuperAdminRole::Support);
    $headers = [superAdminHeaders($owner), superAdminHeaders($owner), superAdminHeaders($other)];

    $this->artisan('super-admin:lockdown', ['--reason' => 'A laptop was stolen'])->expectsOutputToContain('Ended 3 session(s)')->assertSuccessful();

    foreach ($headers as $header) {
        $this->withHeaders($header)->getJson(saUrl('auth/me'))->assertUnauthorized();
    }

    $log = SuperAdminAuditLog::query()->where('action', 'accounts.lockdown')->firstOrFail();

    expect($log->reason)->toBe('A laptop was stolen')
        ->and($log->after)->toBe(['sessions_ended' => 3])
        ->and($log->actor_label)->toStartWith('cli:')
        ->and(app(SuperAdminTokenService::class)->revokeEverything())->toBe(0);

    Notification::assertSentOnDemand(SuperAdminAlertNotification::class, fn (SuperAdminAlertNotification $alert): bool => $alert->event === 'lockdown');

    // Nobody is locked out for good: they sign in again.
    $this->flushHeaders()->postJson(saUrl('auth/login'), ['email' => $owner->email, 'password' => SuperAdminFactory::PASSWORD])->assertOk();
});

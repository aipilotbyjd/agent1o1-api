<?php

namespace App\Models\Auth;

use App\Enums\Auth\ApiKeyAbility;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['workspace_id', 'name', 'hashed_key', 'abilities', 'expires_at'])]
#[Hidden(['hashed_key'])]
class ApiKey extends Model
{
    use HasFactory, HasUuids;

    public const string PREFIX = 'a1o1_live_';

    /** The full shape of a current key, for secret-scanning patterns too. */
    public const string PATTERN = '/^a1o1_live_[0-9A-Za-z]{38}$/';

    private const int SECRET_LENGTH = 32;

    private const int CHECKSUM_LENGTH = 6;

    private const string BASE62_ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public static function hash(string $plainTextKey): string
    {
        return hash('sha256', $plainTextKey);
    }

    /**
     * A new key: `a1o1_live_`, 32 random base62 characters, then a 6-character
     * base62 CRC32 checksum of those 32. The fixed prefix is what lets secret
     * scanners (GitHub's included) recognise a leaked key; the checksum lets
     * them, and `hasValidChecksum()`, tell a real key from a look-alike
     * without a database lookup. Matches `PATTERN`.
     *
     * Keys issued before this format (`ak_` + 40 characters) still work: a
     * key is found by its hash, whatever its shape.
     */
    public static function generatePlainTextKey(): string
    {
        $secret = Str::random(self::SECRET_LENGTH);

        return self::PREFIX.$secret.self::checksum($secret);
    }

    /**
     * Whether a key in the current format has an intact checksum. Keys in any
     * other format (the older `ak_` keys among them) are not judged here.
     */
    public static function hasValidChecksum(string $plainTextKey): bool
    {
        if (preg_match(self::PATTERN, $plainTextKey) !== 1) {
            return false;
        }

        $secret = substr($plainTextKey, strlen(self::PREFIX), self::SECRET_LENGTH);

        return hash_equals(self::checksum($secret), substr($plainTextKey, -self::CHECKSUM_LENGTH));
    }

    /**
     * Whether the key claims the current format (starts with `PREFIX`), so a
     * failed checksum means it was mistyped or forged.
     */
    public static function hasCurrentPrefix(string $plainTextKey): bool
    {
        return str_starts_with($plainTextKey, self::PREFIX);
    }

    private static function checksum(string $secret): string
    {
        $value = crc32($secret);
        $encoded = '';

        do {
            $encoded = self::BASE62_ALPHABET[$value % 62].$encoded;
            $value = intdiv($value, 62);
        } while ($value > 0);

        return str_pad($encoded, self::CHECKSUM_LENGTH, '0', STR_PAD_LEFT);
    }

    public function hasAbility(ApiKeyAbility $ability): bool
    {
        return in_array(ApiKeyAbility::All->value, $this->abilities, strict: true)
            || in_array($ability->value, $this->abilities, strict: true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

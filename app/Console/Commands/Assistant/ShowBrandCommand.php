<?php

namespace App\Console\Commands\Assistant;

use App\Services\Assistant\Branding\BrandRepository;
use Illuminate\Console\Command;

/**
 * Prints the brand as the app resolves it: a quick check after editing
 * the `ASSISTANT_*` env vars that the new name is what will be served.
 */
class ShowBrandCommand extends Command
{
    protected $signature = 'assistant:brand-show';

    protected $description = "Show the assistant's resolved brand.";

    public function handle(BrandRepository $brands): int
    {
        $brand = $brands->current();

        $this->table(['Key', 'Value'], [
            ['name', $brand->name],
            ['tagline', $brand->tagline],
            ['emoji', $brand->emoji],
            ['color', $brand->color],
            ['email', $brand->email()],
            ['email aliases', implode(', ', $brand->emailAliases) ?: '-'],
            ...collect($brand->features)->keys()->map(fn (string $key): array => ["feature: {$key}", $brand->feature($key)])->all(),
        ]);

        return self::SUCCESS;
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CreditPackSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = config('billing.packs', []);

        $this->command->info('Credit Pack Catalog (create these as one-time Prices in Stripe):');
        $this->command->newLine();

        foreach ($catalog as $key => $pack) {
            $priceId = $pack['stripe_price_id'] ?? null;
            $status = $priceId ? "✓ {$priceId}" : '✗ missing — set env var';
            $dollars = '$'.number_format($pack['price_cents'] / 100, 2);

            $this->command->line("  [{$key}] {$pack['label']} — {$dollars} — {$status}");
        }

        $this->command->newLine();
        $missing = collect($catalog)->filter(fn ($p) => empty($p['stripe_price_id']))->count();

        if ($missing > 0) {
            $this->command->warn("{$missing} credit pack(s) have no Stripe Price ID. Set the env vars and re-run.");
        } else {
            $this->command->info('All credit packs are configured.');
        }
    }
}

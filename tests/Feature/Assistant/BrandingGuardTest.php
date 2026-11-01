<?php

use Symfony\Component\Finder\Finder;

/*
 * The assistant's brand name may only be set in config/assistant.php and
 * .env.example. Anywhere else it would survive a rename: see
 * docs/ASSISTANT_BRANDING_PLAN.md.
 */
it('never hardcodes the assistant brand name outside its config', function () {
    $name = (string) config('assistant.brand.name');

    $files = Finder::create()
        ->files()
        ->in(array_filter([
            base_path('app'),
            base_path('routes'),
            base_path('resources/views'),
            base_path('database'),
            is_dir(base_path('lang')) ? base_path('lang') : null,
        ]))
        ->name(['*.php', '*.blade.php']);

    $offenders = [];

    foreach ($files as $file) {
        foreach (preg_split('/\R/', $file->getContents()) as $index => $line) {
            if (preg_match('/\b'.preg_quote($name, '/').'\b/i', $line) === 1) {
                $offenders[] = $file->getRelativePathname().':'.($index + 1);
            }
        }
    }

    expect($offenders)->toBeEmpty();
});

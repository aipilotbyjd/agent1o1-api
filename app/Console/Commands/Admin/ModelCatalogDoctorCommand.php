<?php

namespace App\Console\Commands\Admin;

use App\Services\Ai\Operations\ModelHealthReport;
use Illuminate\Console\Command;

/**
 * The model health report on the command line: exits non-zero when anything
 * is broken, so a deploy can stop on it.
 */
class ModelCatalogDoctorCommand extends Command
{
    protected $signature = 'model-catalog:doctor {--fail-on-warnings : Exit non-zero for warnings too}';

    protected $description = 'Checks the model catalog, routes, prices, endpoints and keys, and reports what is wrong.';

    public function handle(ModelHealthReport $report): int
    {
        $result = $report->run();

        if ($result['findings'] === []) {
            $this->info('The model setup is healthy.');

            return self::SUCCESS;
        }

        $this->table(
            ['Severity', 'Code', 'About', 'Message'],
            array_map(fn (array $finding): array => [
                strtoupper($finding['severity']),
                $finding['code'],
                $finding['subject']['slug'] ?? $finding['subject']['type'],
                $finding['message'],
            ], $result['findings']),
        );

        ['errors' => $errors, 'warnings' => $warnings, 'info' => $info] = $result['summary'];

        $this->line("{$errors} error(s), {$warnings} warning(s), {$info} note(s).");

        return $errors > 0 || ($warnings > 0 && $this->option('fail-on-warnings')) ? self::FAILURE : self::SUCCESS;
    }
}

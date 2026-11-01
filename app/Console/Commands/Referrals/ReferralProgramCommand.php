<?php

namespace App\Console\Commands\Referrals;

use App\Console\Commands\Referrals\Concerns\ManagesReferralsFromConsole;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\ReferralProgramRequest;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralRewardRule;
use App\Services\Referrals\ReferralAdmin;
use App\Services\Referrals\ReferralRuleDescriber;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Create and tune referral programs from the command line: the same
 * operations, validation and audit trail as the admin API.
 *
 *   referrals:program show default
 *   referrals:program create --set name="Launch week" --set default_hold_days=7
 *   referrals:program update launch-week --set is_active=true --set ends_at=2026-11-01
 *   referrals:program update default --set fraud_checks.ip_velocity.max=3
 *   referrals:program default launch-week
 *   referrals:program duplicate default --name="Creator partners"
 *   referrals:program delete launch-week
 */
class ReferralProgramCommand extends Command
{
    use ManagesReferralsFromConsole;

    protected $signature = 'referrals:program
        {action : show, create, update, default, duplicate or delete}
        {program? : The program slug (or id)}
        {--set=* : A setting as key=value; repeat for several}
        {--name= : Name for a duplicated program}
        {--force : Skip the confirmation on delete}';

    protected $description = 'Creates, shows, edits, duplicates or deletes a referral program.';

    public function handle(ReferralAdmin $admin, ReferralRuleDescriber $describer): int
    {
        $action = $this->argument('action');

        try {
            if ($action === 'create') {
                return $this->create($admin);
            }

            $program = $this->program();

            if ($program === null) {
                return self::FAILURE;
            }

            return match ($action) {
                'show' => $this->show($program, $describer),
                'update' => $this->update($admin, $program),
                'default' => $this->makeDefault($admin, $program),
                'duplicate' => $this->duplicate($admin, $program),
                'delete' => $this->delete($admin, $program),
                default => $this->unknownAction(),
            };
        } catch (ValidationException $e) {
            $this->printErrors($e->errors());

            return self::FAILURE;
        }
    }

    private function create(ReferralAdmin $admin): int
    {
        $data = $this->validateInput($this->parseSets($this->option('set')), ReferralProgramRequest::rulesFor(null));

        if ($data === null) {
            return self::FAILURE;
        }

        $program = $admin->createProgram($data, null);

        $this->info("Created program \"{$program->name}\" ({$program->slug}). Add rules with `referrals:rule add {$program->slug}`.");

        return self::SUCCESS;
    }

    private function update(ReferralAdmin $admin, ReferralProgram $program): int
    {
        if ($this->option('set') === []) {
            $this->error('Nothing to change: pass one or more --set key=value.');

            return self::FAILURE;
        }

        $data = $this->validateInput($this->parseSets($this->option('set')), ReferralProgramRequest::rulesFor($program));

        if ($data === null) {
            return self::FAILURE;
        }

        $admin->updateProgram($program, $data, null);

        $this->info("Updated \"{$program->name}\".");

        return self::SUCCESS;
    }

    private function show(ReferralProgram $program, ReferralRuleDescriber $describer): int
    {
        $this->line("<info>{$program->name}</info> ({$program->slug}): ".($program->isLive() ? 'live' : 'not live').($program->is_default ? ', default' : ''));

        $settings = collect($program->only([
            'is_active', 'starts_at', 'ends_at', 'attribution_window_days', 'claim_window_hours',
            'require_verified_email', 'activation_event', 'activation_min_count', 'activation_window_days',
            'default_hold_days', 'approval_mode', 'revoke_on_partial_refund', 'referrer_monthly_credit_cap',
            'referrer_max_stacked_plan_days', 'referrer_max_referrals_per_month', 'referrer_min_account_age_days',
            'referrer_eligible_plan_ids',
        ]))->merge(collect($program->resolvedFraudChecks())->mapWithKeys(fn ($value, $key) => ["fraud_checks.{$key}" => $value]));

        $this->table(['Setting', 'Value'], $settings->map(fn ($value, $key): array => [$key, $this->display($value)])->values()->all());

        $this->table(
            ['Id', 'Order', 'Rule', 'Terms', 'On'],
            $program->rules()->with('plan')->get()->map(fn (ReferralRewardRule $rule): array => [
                $this->shortId($rule->id),
                $rule->sort_order,
                $rule->name,
                $describer->describe($rule),
                $this->yesNo($rule->isLive()),
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function makeDefault(ReferralAdmin $admin, ReferralProgram $program): int
    {
        $admin->makeDefault($program, null);

        $this->info("\"{$program->name}\" is now the default program.");

        return self::SUCCESS;
    }

    private function duplicate(ReferralAdmin $admin, ReferralProgram $program): int
    {
        $copy = $admin->duplicateProgram($program, null, $this->option('name'));

        $this->info("Created \"{$copy->name}\" ({$copy->slug}) with {$program->rules()->count()} rules. It starts switched off.");

        return self::SUCCESS;
    }

    private function delete(ReferralAdmin $admin, ReferralProgram $program): int
    {
        if (! $this->option('force') && ! $this->confirm("Delete \"{$program->name}\"? Its referrals keep their history.")) {
            return self::SUCCESS;
        }

        $admin->deleteProgram($program, null);

        $this->info("Deleted \"{$program->name}\".");

        return self::SUCCESS;
    }

    private function program(): ?ReferralProgram
    {
        $key = $this->argument('program');

        if ($key === null) {
            $this->error('Name the program: its slug (see `referrals:programs`).');

            return null;
        }

        $program = ReferralProgram::query()->where('slug', $key)->first()
            ?? $this->findByShortId(ReferralProgram::query(), $key, 'program');

        return $program;
    }

    private function unknownAction(): int
    {
        $this->error('Unknown action. Use show, create, update, default, duplicate or delete.');

        return self::FAILURE;
    }

    private function display(mixed $value): string
    {
        return match (true) {
            $value === null => '-',
            is_bool($value) => $this->yesNo($value),
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i'),
            is_array($value) => $value === [] ? '-' : json_encode($value),
            default => (string) $value,
        };
    }
}

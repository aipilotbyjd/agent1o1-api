<?php

namespace App\Console\Commands\Referrals;

use App\Console\Commands\Referrals\Concerns\ManagesReferralsFromConsole;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\ReferralRewardRuleRequest;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralRewardRule;
use App\Services\Referrals\ReferralAdmin;
use App\Services\Referrals\ReferralRuleDescriber;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Add and edit reward rules from the command line. Rules are named by the
 * short id `referrals:program show` prints.
 *
 *   referrals:rule add default --set name="Yearly bonus" --set trigger=first_payment \
 *       --set recipient=referrer --set reward_type=credits --set credits_amount=3000 \
 *       --set conditions.billing_intervals=yearly
 *   referrals:rule update 1a2b3c4d --set duration_days=60
 *   referrals:rule toggle 1a2b3c4d
 *   referrals:rule move 1a2b3c4d --position=0
 *   referrals:rule delete 1a2b3c4d
 */
class ReferralRuleCommand extends Command
{
    use ManagesReferralsFromConsole;

    protected $signature = 'referrals:rule
        {action : add, update, toggle, move or delete}
        {target : The program slug (for add) or the rule id}
        {--set=* : A rule field as key=value; repeat for several}
        {--position= : New position for move, starting at 0}
        {--force : Skip the confirmation on delete}';

    protected $description = 'Adds, edits, switches, reorders or deletes a referral reward rule.';

    public function handle(ReferralAdmin $admin, ReferralRuleDescriber $describer): int
    {
        try {
            if ($this->argument('action') === 'add') {
                return $this->add($admin, $describer);
            }

            $rule = $this->findByShortId(ReferralRewardRule::query(), $this->argument('target'), 'rule');

            if ($rule === null) {
                return self::FAILURE;
            }

            return match ($this->argument('action')) {
                'update' => $this->update($admin, $describer, $rule),
                'toggle' => $this->toggle($admin, $rule),
                'move' => $this->move($admin, $rule),
                'delete' => $this->delete($admin, $rule),
                default => $this->unknownAction(),
            };
        } catch (ValidationException $e) {
            $this->printErrors($e->errors());

            return self::FAILURE;
        }
    }

    private function add(ReferralAdmin $admin, ReferralRuleDescriber $describer): int
    {
        $program = ReferralProgram::query()->where('slug', $this->argument('target'))->first()
            ?? $this->findByShortId(ReferralProgram::query(), $this->argument('target'), 'program');

        if ($program === null) {
            return self::FAILURE;
        }

        $data = $this->validRule(null);

        if ($data === null) {
            return self::FAILURE;
        }

        $rule = $admin->createRule($program, $data, null);

        $this->info("Added rule {$this->shortId($rule->id)}: {$describer->describe($rule->load('plan'))}");

        return self::SUCCESS;
    }

    private function update(ReferralAdmin $admin, ReferralRuleDescriber $describer, ReferralRewardRule $rule): int
    {
        if ($this->option('set') === []) {
            $this->error('Nothing to change: pass one or more --set key=value.');

            return self::FAILURE;
        }

        $data = $this->validRule($rule);

        if ($data === null) {
            return self::FAILURE;
        }

        $admin->updateRule($rule, $data, null);

        $this->info("Updated rule {$this->shortId($rule->id)}: {$describer->describe($rule->refresh()->load('plan'))}");

        return self::SUCCESS;
    }

    private function toggle(ReferralAdmin $admin, ReferralRewardRule $rule): int
    {
        $admin->toggleRule($rule, null);

        $this->info("Rule \"{$rule->name}\" is now ".($rule->is_active ? 'on' : 'off').'.');

        return self::SUCCESS;
    }

    private function move(ReferralAdmin $admin, ReferralRewardRule $rule): int
    {
        $position = $this->option('position');

        if (! is_numeric($position) || (int) $position < 0) {
            $this->error('Pass --position=<number>, starting at 0.');

            return self::FAILURE;
        }

        $program = $rule->program;
        $ids = $program->rules()->pluck('id')->reject(fn (string $id): bool => $id === $rule->id)->values()->all();
        array_splice($ids, min((int) $position, count($ids)), 0, [$rule->id]);

        $admin->reorderRules($program, $ids, null);

        $this->info("Moved \"{$rule->name}\" to position {$position}.");

        return self::SUCCESS;
    }

    private function delete(ReferralAdmin $admin, ReferralRewardRule $rule): int
    {
        if (! $this->option('force') && ! $this->confirm("Delete rule \"{$rule->name}\"? Rewards it already gave are kept.")) {
            return self::SUCCESS;
        }

        $admin->deleteRule($rule, null);

        $this->info("Deleted rule \"{$rule->name}\".");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validRule(?ReferralRewardRule $rule): ?array
    {
        $data = $this->validateInput($this->parseSets($this->option('set')), ReferralRewardRuleRequest::rulesFor($rule));

        if ($data === null) {
            return null;
        }

        $errors = ReferralRewardRuleRequest::consistencyErrors($rule, $data);

        if ($errors !== []) {
            $this->printErrors($errors);

            return null;
        }

        return $data;
    }

    private function unknownAction(): int
    {
        $this->error('Unknown action. Use add, update, toggle, move or delete.');

        return self::FAILURE;
    }
}

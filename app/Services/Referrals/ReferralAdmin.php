<?php

namespace App\Services\Referrals;

use App\Actions\Referrals\GrantManualReferralRewardAction;
use App\Actions\Referrals\GrantReferralRewardAction;
use App\Actions\Referrals\RevokeReferralRewardAction;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralRewardType;
use App\Enums\Referrals\ReferralStatus;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralBlockedDomain;
use App\Models\Referrals\ReferralCode;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralRewardRule;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every change a super admin can make to the referral program, shared by
 * the admin API and the `referrals:*` artisan commands so both behave (and
 * are audited) identically. `$admin` is null when the change comes from
 * the command line; the audit log then names the command line as the actor.
 *
 * Input is expected to be validated already (see the admin Form Requests'
 * static `rulesFor()` methods); refusals that depend on state rather than
 * input (deleting the default program, say) throw a `ValidationException`.
 */
class ReferralAdmin
{
    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly ReferralLifecycle $lifecycle,
        private readonly GrantReferralRewardAction $grant,
        private readonly RevokeReferralRewardAction $revoke,
        private readonly GrantManualReferralRewardAction $grantManual,
    ) {}

    // ─── Programs ─────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    public function createProgram(array $data, ?SuperAdmin $admin): ReferralProgram
    {
        $data['slug'] ??= $this->uniqueSlug($data['name']);

        $program = ReferralProgram::query()->create($data);

        $this->audit->record($admin, 'referral_program.created', $program, null, $program->attributesToArray());

        return $program;
    }

    /**
     * Updates settings. `fraud_checks` is merged into the program's own, so
     * changing one check never resets the others.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProgram(ReferralProgram $program, array $data, ?SuperAdmin $admin): ReferralProgram
    {
        $original = $program->getAttributes();

        if (array_key_exists('fraud_checks', $data) && $data['fraud_checks'] !== null) {
            $data['fraud_checks'] = array_replace_recursive($program->fraud_checks ?? [], $data['fraud_checks']);
        }

        $program->update($data);

        $this->audit->recordChanges($admin, 'referral_program.updated', $program, $original);

        return $program;
    }

    public function deleteProgram(ReferralProgram $program, ?SuperAdmin $admin): void
    {
        if ($program->is_default) {
            throw ValidationException::withMessages(['program' => 'Make another program the default before deleting this one.']);
        }

        $program->delete();

        $this->audit->record($admin, 'referral_program.deleted', $program);
    }

    public function makeDefault(ReferralProgram $program, ?SuperAdmin $admin): ReferralProgram
    {
        if (! $program->is_active) {
            throw ValidationException::withMessages(['program' => 'Only an active program can be the default.']);
        }

        $previous = ReferralProgram::query()->where('is_default', true)->value('id');

        $program->update(['is_default' => true]);

        $this->audit->record($admin, 'referral_program.made_default', $program, ['default_program_id' => $previous], ['default_program_id' => $program->id]);

        return $program;
    }

    /**
     * Copies a program and its rules. The copy starts inactive and not the
     * default, so nothing changes until it is switched on.
     */
    public function duplicateProgram(ReferralProgram $program, ?SuperAdmin $admin, ?string $name = null): ReferralProgram
    {
        $copy = DB::transaction(function () use ($program, $name): ReferralProgram {
            $copy = $program->replicate(['is_default', 'is_active']);
            $copy->name = $name ?? "{$program->name} (copy)";
            $copy->slug = $this->uniqueSlug($copy->name);
            $copy->is_default = false;
            $copy->is_active = false;
            $copy->save();

            foreach ($program->rules as $rule) {
                $copy->rules()->save($rule->replicate());
            }

            return $copy;
        });

        $this->audit->record($admin, 'referral_program.duplicated', $copy, ['source_program_id' => $program->id], $copy->attributesToArray());

        return $copy;
    }

    // ─── Rules ────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRule(ReferralProgram $program, array $data, ?SuperAdmin $admin): ReferralRewardRule
    {
        $data['sort_order'] ??= (int) $program->rules()->max('sort_order') + 1;

        $rule = $program->rules()->create($data);

        $this->audit->record($admin, 'referral_rule.created', $rule, null, $rule->attributesToArray());

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRule(ReferralRewardRule $rule, array $data, ?SuperAdmin $admin): ReferralRewardRule
    {
        $original = $rule->getAttributes();

        $rule->update($data);

        $this->audit->recordChanges($admin, 'referral_rule.updated', $rule, $original);

        return $rule;
    }

    public function deleteRule(ReferralRewardRule $rule, ?SuperAdmin $admin): void
    {
        $rule->delete();

        $this->audit->record($admin, 'referral_rule.deleted', $rule, $rule->attributesToArray());
    }

    public function toggleRule(ReferralRewardRule $rule, ?SuperAdmin $admin): ReferralRewardRule
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        $this->audit->record($admin, 'referral_rule.toggled', $rule, ['is_active' => ! $rule->is_active], ['is_active' => $rule->is_active]);

        return $rule;
    }

    /**
     * @param  list<string>  $ruleIds
     */
    public function reorderRules(ReferralProgram $program, array $ruleIds, ?SuperAdmin $admin): void
    {
        if ($program->rules()->whereIn('id', $ruleIds)->count() !== count($ruleIds)) {
            throw ValidationException::withMessages(['rule_ids' => 'Every rule must belong to this program.']);
        }

        DB::transaction(function () use ($program, $ruleIds): void {
            foreach ($ruleIds as $position => $id) {
                $program->rules()->whereKey($id)->first()?->update(['sort_order' => $position]);
            }
        });

        $this->audit->record($admin, 'referral_rule.reordered', $program, null, ['rule_ids' => $ruleIds]);
    }

    // ─── Codes ────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCode(ReferralCode $code, array $data, ?SuperAdmin $admin): ReferralCode
    {
        $original = $code->getAttributes();

        if (isset($data['code'])) {
            $data['code'] = mb_strtolower($data['code']);
        }

        $code->update($data);

        $this->audit->recordChanges($admin, 'referral_code.updated', $code, $original);

        return $code;
    }

    // ─── Referrals ────────────────────────────────────────────

    /**
     * Marks a referral fraudulent (or otherwise ineligible) and withdraws
     * every reward it produced.
     */
    public function rejectReferral(Referral $referral, string $reason, ?SuperAdmin $admin): Referral
    {
        if ($referral->isRejected()) {
            throw ValidationException::withMessages(['referral' => 'This referral is already rejected.']);
        }

        $previous = $referral->status;

        $this->lifecycle->reject($referral, $reason);

        $this->audit->record($admin, 'referral.rejected', $referral, ['status' => $previous->value], ['status' => ReferralStatus::Rejected->value, 'reason' => $reason]);

        return $referral;
    }

    public function restoreReferral(Referral $referral, ?SuperAdmin $admin): Referral
    {
        if (! $referral->isRejected()) {
            throw ValidationException::withMessages(['referral' => 'Only a rejected referral can be restored.']);
        }

        $reason = $referral->rejection_reason;

        $this->lifecycle->restore($referral);

        $this->audit->record($admin, 'referral.restored', $referral, ['status' => ReferralStatus::Rejected->value, 'reason' => $reason], ['status' => $referral->status->value]);

        return $referral;
    }

    // ─── Rewards ──────────────────────────────────────────────

    /**
     * Approves a reward held for manual review. It then follows its normal
     * hold: granted now if the hold has passed, else by
     * `referrals:grant-pending` when it does.
     */
    public function approveReward(ReferralReward $reward, ?SuperAdmin $admin): ReferralReward
    {
        if ($reward->status !== ReferralRewardStatus::AwaitingApproval) {
            throw ValidationException::withMessages(['reward' => 'Only a reward awaiting approval can be approved.']);
        }

        $reward->update(['status' => ReferralRewardStatus::Pending]);

        if ($reward->grant_after === null || $reward->grant_after->isPast()) {
            $this->grant->execute($reward, $admin);
        }

        $this->audit->record($admin, 'referral_reward.approved', $reward, ['status' => ReferralRewardStatus::AwaitingApproval->value], ['status' => $reward->status->value]);

        return $reward;
    }

    public function grantRewardNow(ReferralReward $reward, ?SuperAdmin $admin): ReferralReward
    {
        if (! $reward->status->isOpen()) {
            throw ValidationException::withMessages(['reward' => 'Only a pending or awaiting reward can be granted.']);
        }

        $previous = $reward->status;

        $this->grant->execute($reward, $admin);

        $this->audit->record($admin, 'referral_reward.granted_early', $reward, ['status' => $previous->value], ['status' => ReferralRewardStatus::Granted->value]);

        return $reward;
    }

    public function revokeReward(ReferralReward $reward, string $reason, ?SuperAdmin $admin): ReferralReward
    {
        if ($reward->status === ReferralRewardStatus::Revoked) {
            throw ValidationException::withMessages(['reward' => 'This reward is already revoked.']);
        }

        $previous = $reward->status;

        $this->revoke->execute($reward, $reason);

        $this->audit->record($admin, 'referral_reward.revoked', $reward, ['status' => $previous->value], ['status' => ReferralRewardStatus::Revoked->value, 'reason' => $reason]);

        return $reward;
    }

    /**
     * A goodwill reward handed out directly, granted immediately.
     *
     * @param  array{reward_type: ReferralRewardType, credits?: ?int, plan_id?: ?string, duration_days?: ?int, amount_cents?: ?int, trial_days?: ?int}  $values
     */
    public function grantManualReward(User $recipient, ?Workspace $workspace, array $values, ?string $notes, ?SuperAdmin $admin): ReferralReward
    {
        $reward = $this->grantManual->execute($recipient, $workspace, $values, $admin, $notes);

        $this->audit->record($admin, 'referral_reward.manual_granted', $reward, null, $reward->attributesToArray());

        return $reward;
    }

    // ─── Blocked domains ──────────────────────────────────────

    public function blockDomain(string $domain, ?string $reason, ?SuperAdmin $admin): ReferralBlockedDomain
    {
        $blocked = ReferralBlockedDomain::query()->create(['domain' => $domain, 'reason' => $reason]);

        $this->audit->record($admin, 'referral_blocked_domain.created', $blocked, null, $blocked->only(['domain', 'reason']));

        return $blocked;
    }

    public function unblockDomain(ReferralBlockedDomain $domain, ?SuperAdmin $admin): void
    {
        $domain->delete();

        $this->audit->record($admin, 'referral_blocked_domain.deleted', $domain, $domain->only(['domain', 'reason']));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'program';
        $slug = $base;
        $suffix = 2;

        while (ReferralProgram::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}

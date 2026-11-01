<?php

namespace App\Enums\SuperAdmin;

/**
 * A super admin holds exactly one role, platform-wide (unlike a workspace
 * role, which is per workspace). See `config/super_admin_roles.php`.
 */
enum SuperAdminRole: string
{
    case Owner = 'owner';
    case Operator = 'operator';
    case Finance = 'finance';
    case Support = 'support';
    case Viewer = 'viewer';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function allows(SuperAdminAbility $ability): bool
    {
        $granted = config("super_admin_roles.roles.{$this->value}", []);

        return in_array('*', $granted, true) || in_array($ability->value, $granted, true);
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        return collect(SuperAdminAbility::cases())
            ->filter(fn (SuperAdminAbility $ability): bool => $this->allows($ability))
            ->map(fn (SuperAdminAbility $ability): string => $ability->value)
            ->values()
            ->all();
    }
}

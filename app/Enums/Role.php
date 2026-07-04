<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * Returns the full permission set for this role via composition:
     * each tier only lists its delta; lower tiers are spread in automatically.
     * Owner always gets every Permission case.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Viewer => Permission::viewOnly(),
            self::Member => [...self::Viewer->permissions(), ...Permission::memberGrants()],
            self::Editor => [...self::Member->permissions(), ...Permission::editorGrants()],
            self::Admin => [...self::Editor->permissions(), ...Permission::adminGrants()],
            self::Owner => Permission::cases(),
        };
    }

    /** @return array<string> */
    public function permissionValues(): array
    {
        return array_map(fn (Permission $p) => $p->value, $this->permissions());
    }

    /** Owner is never directly assignable via invitation or role update. */
    public static function assignable(): array
    {
        return array_values(array_filter(self::cases(), fn (Role $r) => $r !== self::Owner));
    }

    /** @return array<string> */
    public static function assignableValues(): array
    {
        return array_map(fn (Role $r) => $r->value, self::assignable());
    }
}

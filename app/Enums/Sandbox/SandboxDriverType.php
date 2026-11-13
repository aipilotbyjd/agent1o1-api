<?php

namespace App\Enums\Sandbox;

use App\Services\Sandbox\Drivers\DaytonaSandboxDriver;
use App\Services\Sandbox\Drivers\E2BSandboxDriver;
use App\Services\Sandbox\Drivers\PistonSandboxDriver;
use App\Services\Sandbox\Drivers\RunnerSandboxDriver;
use App\Services\Sandbox\SandboxDriver;

/**
 * The kinds of provider the platform can talk to. Adding one is a driver
 * class plus a case here: the panel, router and billing need no change.
 */
enum SandboxDriverType: string
{
    case E2B = 'e2b';
    case Daytona = 'daytona';
    case Piston = 'piston';
    case Runner = 'runner';

    public function label(): string
    {
        return match ($this) {
            self::E2B => 'E2B cloud sandboxes',
            self::Daytona => 'Daytona sandboxes (Daytona Cloud or your own install)',
            self::Piston => 'Piston code runner (your own separate server)',
            self::Runner => 'Agent1o1 runner (your own separate sandbox servers)',
        };
    }

    /**
     * @return class-string<SandboxDriver>
     */
    public function driverClass(): string
    {
        return match ($this) {
            self::E2B => E2BSandboxDriver::class,
            self::Daytona => DaytonaSandboxDriver::class,
            self::Piston => PistonSandboxDriver::class,
            self::Runner => RunnerSandboxDriver::class,
        };
    }

    /**
     * Whether this kind can run code in the given mode. Piston has no
     * computers that last, so it only does one-off runs.
     */
    public function supports(SandboxMode $mode): bool
    {
        return match ($this) {
            self::Piston => $mode === SandboxMode::OneShot,
            default => true,
        };
    }

    /**
     * Whether the provider is reached at an address the operator types in
     * (and so must pass the address checks).
     */
    public function usesCustomUrl(): bool
    {
        return in_array($this, [self::Piston, self::Runner], true);
    }

    /**
     * The config keys this kind accepts, with their rules.
     *
     * @return array<string, array<int, mixed>>
     */
    public function configRules(): array
    {
        return match ($this) {
            self::E2B => [
                'api_url' => ['nullable', 'url:https', 'max:500'],
                'domain' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9.-]+$/i'],
                'template' => ['nullable', 'string', 'max:120'],
            ],
            self::Daytona => [
                'api_url' => ['nullable', 'url:https', 'max:500'],
                'toolbox_url' => ['nullable', 'url:https', 'max:500'],
                'snapshot' => ['nullable', 'string', 'max:200'],
                'target' => ['nullable', 'string', 'max:100'],
                'network_block_all' => ['nullable', 'boolean'],
            ],
            self::Piston, self::Runner => [
                'url' => ['required', 'string', 'max:500'],
            ],
        };
    }

    /**
     * The credential keys this kind accepts (write-only).
     *
     * @return array<string, array<int, mixed>>
     */
    public function credentialRules(): array
    {
        return match ($this) {
            self::E2B, self::Daytona => ['api_key' => ['nullable', 'string', 'max:500']],
            self::Piston => ['token' => ['nullable', 'string', 'max:500']],
            self::Runner => ['token' => ['nullable', 'string', 'max:500']],
        };
    }

    /**
     * The credential that must be present for the provider to count as set up.
     */
    public function requiredCredential(): ?string
    {
        return match ($this) {
            self::E2B, self::Daytona => 'api_key',
            self::Runner => 'token',
            self::Piston => null,
        };
    }
}

<?php

use App\Services\SuperAdmin\Dashboards\DashboardAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

function requestFrom(string $ip): Request
{
    return Request::create('/horizon', 'GET', server: ['REMOTE_ADDR' => $ip]);
}

it('defines the Horizon and Pulse gates', function () {
    expect(Gate::has('viewHorizon'))->toBeTrue()
        ->and(Gate::has('viewPulse'))->toBeTrue();
});

it('lets nobody in while no dashboard address is configured', function () {
    config(['super_admin_security.dashboard_ips' => []]);

    expect(app(DashboardAccess::class)->allows(requestFrom('203.0.113.9')))->toBeFalse();
});

it('lets in only the listed addresses and ranges', function () {
    config(['super_admin_security.dashboard_ips' => ['203.0.113.0/24', '198.51.100.7']]);

    $access = app(DashboardAccess::class);

    expect($access->allows(requestFrom('203.0.113.9')))->toBeTrue()
        ->and($access->allows(requestFrom('198.51.100.7')))->toBeTrue()
        ->and($access->allows(requestFrom('198.51.100.8')))->toBeFalse()
        ->and($access->allows(requestFrom('10.0.0.1')))->toBeFalse();
});

it('closes the dashboards while the master switch is off', function () {
    config(['super_admin_security.dashboard_ips' => ['203.0.113.0/24'], 'super_admin.enabled' => false]);

    expect(app(DashboardAccess::class)->allows(requestFrom('203.0.113.9')))->toBeFalse();
});

it('is open in the local environment, as Horizon and Pulse are by default', function () {
    config(['super_admin_security.dashboard_ips' => []]);
    app()->detectEnvironment(fn () => 'local');

    expect(app(DashboardAccess::class)->allows(requestFrom('203.0.113.9')))->toBeTrue();
});

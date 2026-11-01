<?php

namespace App\Http\Requests\Api\Internal\V1\Dashboard;

use App\Services\Dashboard\DashboardWindow;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The `days` window shared by every dashboard endpoint. Capped at a year
 * because the series these endpoints return is one point per day and the
 * client has to draw all of them: a caller wanting a longer history wants a
 * report, not a dashboard.
 */
class DashboardWindowRequest extends FormRequest
{
    /**
     * The window a caller gets when they don't ask for one: long enough to
     * cover a monthly billing period, which is what the credit numbers are
     * measured against.
     */
    private const int DEFAULT_DAYS = 30;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function window(): DashboardWindow
    {
        return DashboardWindow::ofDays((int) ($this->validated('days') ?? self::DEFAULT_DAYS));
    }
}

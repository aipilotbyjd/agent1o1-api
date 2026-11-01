<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Revenue;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\SuperAdmin\Revenue\RevenueMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevenueController extends Controller
{
    /**
     * What `GET /revenue/export` can write, each a method on `datasets()`.
     *
     * @var list<string>
     */
    private const EXPORTS = ['subscriptions', 'plans', 'failed-payments', 'top-customers', 'timeseries'];

    public function __construct(
        private readonly RevenueMetrics $revenue,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function overview(Request $request)
    {
        return ApiResponse::success($this->revenue->overview($this->days($request)));
    }

    public function mrrMovement(Request $request)
    {
        return ApiResponse::success($this->revenue->mrrMovement(max(1, min($request->integer('months', 12), 36))));
    }

    public function timeseries(Request $request)
    {
        [$from, $to, $granularity] = $this->timeseriesRange($request);

        return ApiResponse::success($this->revenue->timeseries($from, $to, $granularity));
    }

    public function plans()
    {
        return ApiResponse::success($this->revenue->plans());
    }

    public function churn(Request $request)
    {
        return ApiResponse::success($this->revenue->churn($this->days($request)));
    }

    public function trials(Request $request)
    {
        return ApiResponse::success($this->revenue->trials($this->days($request)));
    }

    public function failedPayments()
    {
        return ApiResponse::success($this->revenue->failedPayments());
    }

    public function cohorts(Request $request)
    {
        return ApiResponse::success($this->revenue->cohorts(max(1, min($request->integer('months', 12), 24))));
    }

    public function ltv()
    {
        return ApiResponse::success($this->revenue->ltv());
    }

    public function credits(Request $request)
    {
        return ApiResponse::success($this->revenue->credits($this->days($request)));
    }

    public function topCustomers(Request $request)
    {
        return ApiResponse::success($this->revenue->topCustomers(max(1, min($request->integer('limit', 10), 100))));
    }

    /**
     * Any of the tabular views as CSV. Audited: it is a bulk copy of customer revenue.
     */
    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'dataset' => ['required', Rule::in(self::EXPORTS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'granularity' => ['nullable', Rule::in(['day', 'week', 'month'])],
        ]);

        $dataset = $request->string('dataset')->toString();

        $rows = match ($dataset) {
            'subscriptions' => $this->revenue->subscriptionRows(),
            'plans' => $this->revenue->plans()['plans'],
            'failed-payments' => $this->revenue->failedPayments()['subscriptions'],
            'top-customers' => array_map(fn (array $row): array => [...$row, 'plans' => implode('|', $row['plans'])], $this->revenue->topCustomers(100)['top']),
            'timeseries' => $this->revenue->timeseries(...$this->timeseriesRange($request))['points'],
        };

        $this->audit->record($request->user('super-admin'), 'revenue.exported', null, null, ['dataset' => $dataset, 'rows' => count($rows)]);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            if ($rows !== []) {
                fputcsv($out, array_keys($rows[0]));
            }

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($value): string => $this->csvCell($value), $row));
            }

            fclose($out);
        }, "revenue-{$dataset}-".now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * A cell that starts with = + - or @ would run as a formula in a
     * spreadsheet (workspace names are chosen by customers), so it is quoted.
     */
    private function csvCell(mixed $value): string
    {
        $text = is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value);

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'{$text}" : $text;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function timeseriesRange(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'granularity' => ['nullable', Rule::in(['day', 'week', 'month'])],
        ]);

        $to = $request->filled('to') ? CarbonImmutable::parse($request->string('to')->toString(), 'UTC') : CarbonImmutable::now('UTC');
        $from = $request->filled('from') ? CarbonImmutable::parse($request->string('from')->toString(), 'UTC') : $to->subDays(29);

        abort_if($from->diffInDays($to, true) > 365, 422, 'Pick a range of at most a year.');

        return [$from, $to, $request->string('granularity', 'day')->toString()];
    }

    private function days(Request $request): int
    {
        return max(1, min($request->integer('days', 30), 365));
    }
}

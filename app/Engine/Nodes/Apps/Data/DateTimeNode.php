<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Carbon\Carbon;

class DateTimeNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $date = isset($input->config['date'])
            ? Carbon::parse($input->config['date'], $input->config['timezone'] ?? 'UTC')
            : Carbon::now($input->config['timezone'] ?? 'UTC');

        return match ($operation) {
            'now' => $this->success(['result' => $date->toISOString()]),
            'format' => $this->success(['result' => $date->format($input->config['format'] ?? 'Y-m-d H:i:s')]),
            'add' => $this->success(['result' => $date->add($input->config['unit'] ?? 'days', (int) ($input->config['amount'] ?? 1))->toISOString()]),
            'subtract' => $this->success(['result' => $date->sub($input->config['unit'] ?? 'days', (int) ($input->config['amount'] ?? 1))->toISOString()]),
            'diff' => $this->diff($date, $input),
            'start_of' => $this->success(['result' => $date->startOf($input->config['unit'] ?? 'day')->toISOString()]),
            'end_of' => $this->success(['result' => $date->endOf($input->config['unit'] ?? 'day')->toISOString()]),
            'timestamp' => $this->success(['result' => $date->timestamp]),
            default => $this->fail("DateTime: unknown operation '{$operation}'"),
        };
    }

    private function diff(Carbon $date, NodeInput $input): NodeResult
    {
        $other = Carbon::parse($input->config['other_date'] ?? now());
        $unit = $input->config['unit'] ?? 'seconds';

        $result = match ($unit) {
            'minutes' => $date->diffInMinutes($other),
            'hours' => $date->diffInHours($other),
            'days' => $date->diffInDays($other),
            default => $date->diffInSeconds($other),
        };

        return $this->success(['result' => $result, 'unit' => $unit]);
    }
}

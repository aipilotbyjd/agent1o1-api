<?php

use App\Services\Agents\Approvals\ConditionEvaluator;

it('matches rule conditions against a call\'s arguments', function (array $condition, array $arguments, bool $expected) {
    expect(app(ConditionEvaluator::class)->matches($condition, $arguments))->toBe($expected);
})->with([
    'eq is case-insensitive' => [['field' => 'channel', 'op' => 'eq', 'value' => '#General'], ['channel' => '#general'], true],
    'in a list' => [['field' => 'channel', 'op' => 'in', 'value' => ['#exec', '#board']], ['channel' => '#exec'], true],
    'not in a list' => [['field' => 'channel', 'op' => 'not_in', 'value' => ['#exec']], ['channel' => '#random'], true],
    'numbers compare as numbers' => [['field' => 'amount', 'op' => 'gt', 'value' => 500], ['amount' => 900], true],
    'a missing field never compares' => [['field' => 'amount', 'op' => 'gt', 'value' => 500], [], false],
    'missing' => [['field' => 'cc', 'op' => 'missing'], ['to' => 'a@acme.com'], true],
    'dot paths' => [['field' => 'body.priority', 'op' => 'eq', 'value' => 'high'], ['body' => ['priority' => 'high']], true],
    'contains' => [['field' => 'subject', 'op' => 'contains', 'value' => 'invoice'], ['subject' => 'Your INVOICE is ready'], true],
    'every recipient inside the domain' => [['field' => 'to', 'op' => 'domain_in', 'value' => ['acme.com']], ['to' => 'a@acme.com; b@acme.com'], true],
    'one outside recipient among internal ones' => [['field' => 'to', 'op' => 'domain_not_in', 'value' => ['acme.com']], ['to' => 'a@acme.com, Eve <eve@rival.com>'], true],
    'all recipients internal' => [['field' => 'to', 'op' => 'domain_not_in', 'value' => ['acme.com']], ['to' => ['a@acme.com', 'b@acme.com']], false],
    'bulk sends' => [['field' => 'to', 'op' => 'count_gt', 'value' => 2], ['to' => 'a@x.com, b@x.com, c@x.com'], true],
    'a regex without delimiters' => [['field' => 'body', 'op' => 'matches', 'value' => '\d{4}-\d{4}-\d{4}-\d{4}'], ['body' => 'card 4111-1111-1111-1111'], true],
    'an invalid regex never matches' => [['field' => 'body', 'op' => 'matches', 'value' => '/(unclosed/'], ['body' => 'anything'], false],
]);

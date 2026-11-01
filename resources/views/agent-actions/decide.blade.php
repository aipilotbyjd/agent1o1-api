<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approve action: {{ $action->agent?->name }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f6f6f7; color: #1f2328; margin: 0; padding: 24px 16px; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #e3e4e8; border-radius: 12px; padding: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #656d76; font-size: 14px; }
        pre { background: #f6f8fa; border-radius: 8px; padding: 12px; overflow-x: auto; font-size: 13px; white-space: pre-wrap; word-break: break-word; }
        textarea { width: 100%; box-sizing: border-box; border: 1px solid #d0d7de; border-radius: 8px; padding: 8px; font: inherit; min-height: 72px; }
        .buttons { display: flex; gap: 8px; margin-top: 12px; }
        button { flex: 1; border: 0; border-radius: 8px; padding: 12px; font-size: 15px; font-weight: 600; cursor: pointer; }
        .approve { background: #1f883d; color: #fff; }
        .reject { background: #eef0f2; color: #cf222e; }
        .status { font-weight: 600; }
    </style>
</head>
<body>
<div class="card">
    <h1>{{ $action->agent?->name }} wants to run {{ str_replace('_', ' ', $action->tool_name) }}</h1>
    @if (filled($action->reason['detail'] ?? null))
        <p class="muted">Why it asked: {{ $action->reason['detail'] }}</p>
    @endif

    <pre>{{ json_encode($action->effectiveArguments(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>

    @if ($decided)
        <p class="status">This action is {{ str_replace('_', ' ', $action->status->value) }}.</p>
    @else
        <form method="POST" action="{{ $formUrl }}">
            @csrf
            <label class="muted" for="note">Note for the agent (optional)</label>
            <textarea id="note" name="note" placeholder="e.g. Send it to the team list instead"></textarea>
            <div class="buttons">
                <button class="approve" type="submit" name="decision" value="approve">Approve</button>
                <button class="reject" type="submit" name="decision" value="reject">Reject</button>
            </div>
        </form>
    @endif
</div>
</body>
</html>

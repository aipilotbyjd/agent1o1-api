<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Email preferences</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f6f6f7; color: #1f2328; margin: 0; padding: 24px 16px; }
        .card { max-width: 480px; margin: 0 auto; background: #fff; border: 1px solid #e3e4e8; border-radius: 12px; padding: 24px; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        p { color: #656d76; font-size: 14px; }
        button { border: 0; border-radius: 8px; padding: 12px 16px; font-size: 15px; font-weight: 600; cursor: pointer; background: #1f2328; color: #fff; }
    </style>
</head>
<body>
<div class="card">
    @if ($unsubscribed)
        <h1>You are unsubscribed</h1>
        <p>You will no longer receive marketing emails from {{ config('app.name') }}. We may still email you about your account, such as security notices or service incidents.</p>
    @else
        <h1>Unsubscribe from marketing emails?</h1>
        <p>You will stop receiving product news and offers. Messages about your account and service incidents will still reach you.</p>
        <form method="POST" action="{{ $formUrl }}">
            @csrf
            <button type="submit">Unsubscribe</button>
        </form>
    @endif
</div>
</body>
</html>

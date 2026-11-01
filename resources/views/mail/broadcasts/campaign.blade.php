<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaignSubject }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#18181b;">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;padding:32px;line-height:1.6;font-size:16px;">
        {!! $bodyHtml !!}
    </div>
    <div style="max-width:600px;margin:16px auto 0;font-size:12px;color:#71717a;text-align:center;">
        @if ($unsubscribeUrl)
            You are receiving this because you have an account with {{ config('app.name') }}.
            <a href="{{ $unsubscribeUrl }}" style="color:#71717a;">Unsubscribe</a> from these emails.
        @else
            This is a service message about your {{ config('app.name') }} account.
        @endif
    </div>
</body>
</html>

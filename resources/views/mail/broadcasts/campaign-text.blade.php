{{ $bodyText }}

--
@if ($unsubscribeUrl)
Unsubscribe from these emails: {{ $unsubscribeUrl }}
@else
This is a service message about your {{ config('app.name') }} account.
@endif

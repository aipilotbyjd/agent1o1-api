<?php

namespace App\Nodes\Integrations\Calendly;

use App\Models\Runs\Run;

class CalendlyGetCurrentUserNode extends AbstractCalendlyNode
{
    public function type(): string
    {
        return 'calendly_get_current_user';
    }

    public function name(): string
    {
        return 'Calendly: Get Current User';
    }

    public function description(): string
    {
        return 'Gets the connected person’s name, email, timezone and personal booking page.';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'required' => [], 'properties' => [...$this->credentialFields()]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $me = $this->me($run, $config);

        return ['name' => $me['name'], 'email' => $me['email'], 'timezone' => $me['timezone'], 'booking_page' => $me['scheduling_url']];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['title' => 'Name', 'description' => 'Their name.'],
                'email' => ['title' => 'Email', 'description' => 'Their email.'],
                'timezone' => ['title' => 'Timezone', 'description' => 'Their timezone.'],
                'booking_page' => ['title' => 'Booking page', 'description' => 'Their personal booking page.'],
            ],
        ];
    }
}

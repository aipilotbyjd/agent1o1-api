<?php

namespace App\Nodes\Integrations\Email;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

/**
 * Checks an address without sending to it: its shape, and that its domain
 * can receive mail (an MX or address record). Needs no connected account.
 */
class EmailValidateAddressNode implements DeclaresEffect, NodeContract
{
    /**
     * Domains people use for throwaway addresses.
     *
     * @var list<string>
     */
    private const array DISPOSABLE = ['mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com', 'trashmail.com', 'yopmail.com', 'sharklasers.com', 'getnada.com', 'throwawaymail.com'];

    /**
     * Mailbox providers that give anyone a free address.
     *
     * @var list<string>
     */
    private const array FREE = ['gmail.com', 'googlemail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'live.com', 'icloud.com', 'aol.com', 'proton.me', 'protonmail.com', 'gmx.com', 'mail.com', 'zoho.com'];

    public function type(): string
    {
        return 'email_validate_address';
    }

    public function category(): string
    {
        return 'smtp';
    }

    public function name(): string
    {
        return 'Email: Validate Address';
    }

    public function description(): string
    {
        return 'Checks that an email address is well formed and that its domain accepts mail, and flags free and throwaway providers. Nothing is sent and no account is needed.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                'email' => Field::text('Email address', null, 'name@example.com'),
                'check_domain' => Field::boolean('Check the domain accepts mail', 'Looks up the domain\'s mail records (a DNS lookup).', true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = strtolower(trim((string) ($config['email'] ?? '')));
        $wellFormed = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        $domain = $wellFormed ? substr(strrchr($email, '@'), 1) : null;
        $checkDomain = filter_var($config['check_domain'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $acceptsMail = null;

        if ($wellFormed && $checkDomain) {
            $acceptsMail = checkdnsrr($domain.'.', 'MX') || checkdnsrr($domain.'.', 'A') || checkdnsrr($domain.'.', 'AAAA');
        }

        return [
            'email' => $email,
            'valid' => $wellFormed && $acceptsMail !== false,
            'well_formed' => $wellFormed,
            'domain' => $domain,
            'domain_accepts_mail' => $acceptsMail,
            'local_part' => $wellFormed ? substr($email, 0, (int) strrpos($email, '@')) : null,
            'is_free_provider' => $domain !== null && in_array($domain, self::FREE, true),
            'is_disposable' => $domain !== null && in_array($domain, self::DISPOSABLE, true),
        ];
    }
}

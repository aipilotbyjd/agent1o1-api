<?php

namespace App\Contracts;

interface AgentRunnable
{
    /**
     * Run the agent with the given message and return the full response.
     *
     * @param  array<string, mixed>  $context
     */
    public function run(string $message, array $context = []): string;
}

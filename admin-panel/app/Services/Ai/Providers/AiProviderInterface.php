<?php

namespace App\Services\Ai\Providers;

interface AiProviderInterface
{
    public function key(): string;

    public function complete(string $prompt, array $context = [], array $options = []): array;

    public function health(): array;

    /**
     * Performs a real, cheap round-trip to the provider's API (not just a
     * "is a key stored" check) so admins can see actual connectivity --
     * bad key, quota exceeded, network/DNS failure, etc -- instead of a
     * generic "failed" message.
     */
    public function testConnection(): array;
}

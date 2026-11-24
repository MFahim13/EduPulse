<?php
declare(strict_types=1);

final class HttpResponse
{
    /** @param array<string, string[]> $headers lower-cased header name => values */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    public function location(): ?string
    {
        return $this->header('location');
    }
}

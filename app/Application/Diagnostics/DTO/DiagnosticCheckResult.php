<?php

declare(strict_types=1);

namespace App\Application\Diagnostics\DTO;

/**
 * Результат одной диагностической проверки для API и UI.
 *
 * @param string               $status  `ok` | `warning` | `failed`
 * @param array<string, mixed> $context
 */
final readonly class DiagnosticCheckResult
{
    public function __construct(
        public string $name,
        public string $status,
        public string $message,
        public array $context = [],
    ) {}

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }

    /**
     * @return array{name: string, status: string, message: string, context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'name'    => $this->name,
            'status'  => $this->status,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}

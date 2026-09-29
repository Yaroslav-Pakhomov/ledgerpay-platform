<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/**
 * Стабильные email demo-пользователей для seed и ручного входа.
 *
 * Используются для связи между seeder'ами без общего контекста в памяти.
 */
final class DemoEmails
{
    public const string ALICE = 'alice@ledgerpay.test';

    public const string BOB = 'bob@ledgerpay.test';

    public const string ACME = 'finance@acme.test';

    public const string BLOCKED = 'blocked@ledgerpay.test';

    public const string ADMIN = 'admin@ledgerpay.test';
}

<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Демо: две записи журнала аудита для бэк-офиса (расследование / dashboard).
 *
 * Исполнитель — {@see DemoEmails::ADMIN}; требует {@see BackofficeUserSeeder} до {@see DemoDataSeeder}.
 */
final class DemoAuditSeeder extends Seeder
{
    /**
     * Создать записи аудита seed с фиксированным request_id demo-seed-request.
     */
    public function run(): void
    {
        $admin = User::query()
            ->where('email', DemoEmails::ADMIN)
            ->first();

        AuditLog::query()->create([
            'actor_user_id' => $admin?->id,
            'action'        => AuditAction::BackofficeDashboardViewed,
            'metadata'      => [
                'seeded' => true,
            ],
            'request_id' => 'demo-seed-request',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'DemoDataSeeder',
        ]);

        AuditLog::query()->create([
            'actor_user_id' => $admin?->id,
            'action'        => AuditAction::BackofficeCustomerViewed,
            'metadata'      => [
                'seeded' => true,
                'note'   => 'Демо-событие для расследования',
            ],
            'request_id' => 'demo-seed-request',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'DemoDataSeeder',
        ]);
    }
}

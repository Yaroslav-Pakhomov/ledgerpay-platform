<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Audit\Services\AuditLogger;
use App\Application\Transaction\DTO\CreateDepositData;
use App\Application\Transaction\Jobs\ProcessTransactionJob;
use App\Application\Transaction\Services\TransactionProcessorService;
use App\Application\Transaction\Services\TransactionService;
use App\Domain\Account\Exceptions\InsufficientFundsException;
use App\Domain\Account\Models\Account;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Customer\Models\Customer;
use App\Domain\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use LogicException;
use Tests\Feature\Api\Concerns\CreatesApiFixtures;
use Tests\TestCase;

/**
 * Feature-тесты immutable audit log.
 *
 * AuditLog — append-only журнал ({@see AuditLogger}):
 * фиксирует кто, что и над какой сущностью сделал. Модель запрещает
 * update/delete ({@see AuditLog::booted()}).
 *
 * Покрывает:
 * - HTTP: POST /login → {@see AuditAction::UserLoggedIn};
 * - HTTP: POST /transactions/deposit (web) → {@see AuditAction::TransactionQueued};
 * - async: {@see ProcessTransactionJob} → TransactionCompleted / TransactionFailed;
 * - backoffice: GET /backoffice/audit-logs — access control + фильтр action;
 * - backoffice views: GET /backoffice, GET /backoffice/customers/{uuid};
 * - domain: immutability → {@see LogicException}.
 */
final class AuditLogTest extends TestCase
{
    use CreatesApiFixtures;
    use RefreshDatabase;

    /**
     * Успешный login создаёт запись в `audit_logs` с действием user_logged_in.
     */
    public function test_audit_log_is_created_for_login(): void
    {
        User::factory()->create([
            'email'    => 'alice@example.com',
            'password' => 'StrongPassword123!',
        ]);

        $this->post('/login', [
            'email'    => 'alice@example.com',
            'password' => 'StrongPassword123!',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserLoggedIn->value,
        ]);
    }

    /**
     * Попытка изменить существующую запись audit log завершается LogicException.
     */
    public function test_audit_log_is_immutable_on_update(): void
    {
        $log = AuditLog::query()->create([
            'action' => AuditAction::UserLoggedIn,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Записи в логировании являются неизменяемыми.');

        $log->update([
            'action' => AuditAction::UserLoggedOut,
        ]);
    }

    /**
     * Попытка удалить запись audit log завершается LogicException.
     */
    public function test_audit_log_is_immutable_on_delete(): void
    {
        $log = AuditLog::query()->create([
            'action' => AuditAction::UserLoggedIn,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Записи в логировании являются неизменяемыми.');

        $log->delete();
    }

    /**
     * Backoffice-пользователь ({@see User::factory()::backOffice()}) может открыть
     * страницу журнала аудита.
     */
    public function test_backoffice_can_view_audit_logs(): void
    {
        $backoffice = User::factory()->backOffice()->create();

        AuditLog::query()->create([
            'actor_user_id' => $backoffice->id,
            'action'        => AuditAction::UserLoggedIn,
        ]);

        $this->actingAs($backoffice);

        $this->get('/backoffice/audit-logs')
            ->assertOk();
    }

    /**
     * Клиентский пользователь авторизован, но не имеет прав бэк-офиса —
     * ответ 403 Forbidden на `/backoffice/audit-logs`.
     */
    public function test_customer_cannot_view_audit_logs(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->forCustomer($customer)->create();

        $this->actingAs($user);

        $this->get('/backoffice/audit-logs')
            ->assertForbidden();
    }

    /**
     * Web deposit создаёт audit-запись transaction_queued с actor_user_id.
     */
    public function test_web_deposit_creates_transaction_queued_audit_log(): void
    {
        Queue::fake();

        $customer = Customer::factory()->create();
        $user = User::factory()->forCustomer($customer)->create();
        $account = Account::factory()->for($customer)->currency('USD')->withBalance(0)->create();

        $this->actingAs($user)->post('/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 10_000,
            'currency'            => 'USD',
        ])->assertRedirect();

        $transaction = Transaction::query()->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'action'        => AuditAction::TransactionQueued->value,
            'entity_type'   => Transaction::class,
            'entity_id'     => $transaction->id,
            'actor_user_id' => $user->id,
        ]);
    }

    /**
     * Idempotent replay в service (created=false) не создаёт audit сам по себе;
     * web-контроллер дополнительно guard'ит TransactionQueued через auditQueuedIfCreated().
     */
    public function test_idempotent_service_replay_does_not_create_transaction_queued_audit(): void
    {
        Queue::fake();

        $account = $this->createAccount($this->createCustomer());
        $service = app(TransactionService::class);

        $data = new CreateDepositData(
            $account->uuid,
            10_000,
            'USD',
            'audit-idem-key-001',
        );

        $first = $service->deposit($data);
        $second = $service->deposit($data);

        $this->assertTrue($first->created);
        $this->assertFalse($second->created);
        $this->assertSame($first->transaction->id, $second->transaction->id);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::TransactionQueued->value,
        ]);
    }

    /**
     * Успешная обработка job создаёт audit-запись transaction_completed.
     *
     * Worker без HTTP-сессии → actor_user_id = null.
     */
    public function test_job_logs_transaction_completed(): void
    {
        Queue::fake();

        $account = Account::factory()->currency('USD')->withBalance(0)->create();
        $this->actingAsCustomerFor($account);

        $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 50_000,
            'currency'            => 'USD',
        ], ['Idempotency-Key' => 'audit-completed-001']);

        $transaction = Transaction::query()->firstOrFail();

        $this->app['auth']->forgetGuards();

        app(ProcessTransactionJob::class, [
            'transactionId' => $transaction->id,
        ])->handle(app(TransactionProcessorService::class));

        $this->assertDatabaseHas('audit_logs', [
            'action'        => AuditAction::TransactionCompleted->value,
            'entity_type'   => Transaction::class,
            'entity_id'     => $transaction->id,
            'actor_user_id' => null, // worker без HTTP-сессии
        ]);
    }

    /**
     * failed() job после исчерпания retry создаёт audit-запись transaction_failed
     * с metadata exception_class и message.
     */
    public function test_job_logs_transaction_failed(): void
    {
        Queue::fake();

        $account = Account::factory()->currency('USD')->withBalance(1_000)->create();
        $this->actingAsCustomerFor($account);

        $this->postJson('/api/transactions/withdraw', [
            'source_account_uuid' => $account->uuid,
            'amount'              => 5_000,
            'currency'            => 'USD',
        ], ['Idempotency-Key' => 'audit-failed-001']);

        $transaction = Transaction::query()->firstOrFail();
        $exception = new InsufficientFundsException;

        app(ProcessTransactionJob::class, [
            'transactionId' => $transaction->id,
        ])->failed($exception);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => AuditAction::TransactionFailed->value,
            'entity_type' => Transaction::class,
            'entity_id'   => $transaction->id,
        ]);

        $log = AuditLog::query()->where('action', AuditAction::TransactionFailed)->first();
        $this->assertSame(InsufficientFundsException::class, $log->metadata['exception_class'] ?? null);
    }

    /**
     * Backoffice-фильтр ?action= возвращает только записи с указанным действием.
     */
    public function test_backoffice_audit_logs_filters_by_action(): void
    {
        $backoffice = User::factory()->backOffice()->create();

        AuditLog::query()->create(['action' => AuditAction::UserLoggedIn]);
        AuditLog::query()->create(['action' => AuditAction::TransactionCompleted]);

        $this->actingAs($backoffice);

        $this->get('/backoffice/audit-logs?action=transaction_completed')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Backoffice/AuditLogs')
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'transaction_completed')
                ->where('filters.action', 'transaction_completed')
            );
    }

    /**
     * Открытие главной backoffice создаёт запись backoffice_dashboard_viewed.
     *
     * entity — backoffice-пользователь ({@see User}); actor совпадает с entity.
     */
    public function test_backoffice_dashboard_view_logs_audit(): void
    {
        $backoffice = User::factory()->backOffice()->create();

        $this->actingAs($backoffice)
            ->get('/backoffice')
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action'        => AuditAction::BackofficeDashboardViewed->value,
            'entity_type'   => User::class,
            'entity_uuid'   => null,
            'actor_user_id' => $backoffice->id,
        ]);
    }

    /**
     * Просмотр карточки клиента создаёт backoffice_customer_viewed.
     *
     * entity — backoffice-пользователь; snapshot клиента и ID счетов — в metadata
     * (jsonb не сравнивается через {@see self::assertDatabaseHas()}).
     */
    public function test_backoffice_customer_view_logs_audit(): void
    {
        $backoffice = User::factory()->backOffice()->create();
        $customer = Customer::factory()->create();
        $accountIds = $customer->accounts()->pluck('id');

        $this->actingAs($backoffice)
            ->get("/backoffice/customers/{$customer->uuid}")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action'        => AuditAction::BackofficeCustomerViewed->value,
            'entity_type'   => User::class,
            'entity_uuid'   => null,
            'actor_user_id' => $backoffice->id,
        ]);

        $log = AuditLog::query()
            ->where('action', AuditAction::BackofficeCustomerViewed)
            ->firstOrFail();

        $this->assertSame($customer->uuid, $log->metadata['customer']['uuid'] ?? null);
        $this->assertSame($customer->email, $log->metadata['customer']['email'] ?? null);
        $this->assertSame($accountIds->all(), $log->metadata['accounts'] ?? null);
    }
}

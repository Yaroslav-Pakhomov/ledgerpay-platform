<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Requests\Common\CursorPaginatedRequest;
use App\Support\Pagination\CursorPaginationPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\AccountApiTest;
use Tests\Feature\Api\Concerns\CreatesApiFixtures;
use Tests\Feature\Api\TransactionApiTest;
use Tests\TestCase;

/**
 * Feature-тесты cursor pagination в API (контракт ответа и лимиты).
 *
 * Проверяют интеграцию {@see TransactionController::index()}
 * с {@see CursorPaginatedRequest} и
 * {@see CursorPaginationPayload}:
 *
 * - envelope `data` + `meta` (per_page, next_cursor, prev_cursor, has_more_pages);
 * - ограничение query `limit` через `config('ledgerpay.pagination.max_limit')`.
 *
 * Детальные сценарии list API — в {@see TransactionApiTest}
 * и {@see AccountApiTest}; здесь — контракт pagination и config cap.
 */
final class CursorPaginationTest extends TestCase
{
    use CreatesApiFixtures;
    use RefreshDatabase;

    /** GET /api/transactions возвращает CursorPaginationPayload envelope при limit=10. */
    public function test_transactions_api_uses_cursor_pagination_payload(): void
    {
        $account = Account::factory()->create();

        Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Completed,
            'source_account_id'      => null,
            'target_account_id'      => $account->id,
            'amount'                 => 1000,
            'currency'               => 'USD',
            'idempotency_key'        => 'cursor-test-001',
            'idempotency_expires_at' => now()->addDay(),
            'processed_at'           => now(),
        ]);

        $this->actingAsBackoffice();

        $this->getJson('/api/transactions?limit=10')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'per_page',
                    'next_cursor',
                    'prev_cursor',
                    'has_more_pages',
                ],
            ]);
    }

    /** limit выше max_limit урезается до значения из config. */
    public function test_limit_is_capped_by_config(): void
    {
        config()->set('ledgerpay.pagination.max_limit', 25);

        $this->actingAsBackoffice();

        $this->getJson('/api/transactions?limit=1000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25);
    }
}

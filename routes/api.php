<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(base_path('routes/api_v1.php'));

/**
 * Deprecated compatibility alias.
 *
 * Старые /api/* маршруты временно сохранены для клиентов.
 * Стабильный контракт: /api/v1/*.
 */
Route::middleware('api.deprecated')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::middleware('throttle:auth')->group(function (): void {

            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/login', [AuthController::class, 'login']);
        });

        Route::middleware(['auth:sanctum', 'throttle:api-global'])->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware(['auth:sanctum', 'throttle:api-global'])->group(function (): void {
        // Клиенты
        Route::controller(CustomerController::class)->prefix('customers')->group(function (): void {
            // Получение всех клиентов
            Route::get('/', 'index');

            // Создание клиента
            Route::post('/', 'store');

            // Получение клиента
            Route::get('/{uuid}', 'show')->whereUuid('uuid');
        });

        // Счета
        Route::controller(AccountController::class)->prefix('accounts')->group(function (): void {
            // Получение всех счетов
            Route::get('/', 'index');

            // Создание счёта
            Route::post('/', 'store');

            // Получение счёта
            Route::get('/{uuid}', 'show')->whereUuid('uuid');

            // Получение баланса по счёту
            Route::get('/{uuid}/balance', 'balance')->whereUuid('uuid');

            // Получение выписки по счёту
            Route::get('/{uuid}/ledger', 'ledger')->whereUuid('uuid');
        });

        // Транзакции/Операции
        Route::controller(TransactionController::class)->prefix('transactions')->group(function (): void {
            // Получение всех транзакций
            Route::get('/', 'index');

            Route::middleware('throttle:money-movement')->group(function (): void {
                // Пополнения счета
                Route::post('/deposit', 'deposit');

                // Списания со счета
                Route::post('/withdraw', 'withdraw');

                // Перевод между счетами
                Route::post('/transfer', 'transfer');

                // Повтор failed-транзакцию
                Route::post('/{uuid}/retry', 'retry')->whereUuid('uuid');
            });

            // Одна транзакция по uuid
            Route::get('/{uuid}', 'show')->whereUuid('uuid');
        });
    });
});

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('api.auth.')->group(function (): void {
    Route::middleware('throttle:auth')->group(function (): void {

        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/login', [AuthController::class, 'login'])->name('login');
    });

    Route::middleware(['auth:sanctum', 'throttle:api-global'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware(['auth:sanctum', 'throttle:api-global'])->group(function (): void {
    // Клиенты
    Route::controller(CustomerController::class)->prefix('customers')->name('api.customers.')->group(function (): void {
        // Получение всех клиентов
        Route::get('/', 'index')->name('index');

        // Создание клиента
        Route::post('/', 'store')->name('store');

        // Получение клиента
        Route::get('/{uuid}', 'show')->whereUuid('uuid')->name('show');
    });

    // Счета
    Route::controller(AccountController::class)->prefix('accounts')->name('api.accounts.')->group(function (): void {
        // Получение всех счетов
        Route::get('/', 'index')->name('index');

        // Создание счёта
        Route::post('/', 'store')->name('store');

        // Получение счёта
        Route::get('/{uuid}', 'show')->whereUuid('uuid')->name('show');

        // Получение баланса по счёту
        Route::get('/{uuid}/balance', 'balance')->whereUuid('uuid')->name('balance');

        // Получение выписки по счёту
        Route::get('/{uuid}/ledger', 'ledger')->whereUuid('uuid')->name('ledger');
    });

    // Транзакции/Операции
    Route::controller(TransactionController::class)->prefix('transactions')->name('api.transaction.')->group(function (): void {
        // Получение всех транзакций
        Route::get('/', 'index')->name('index');

        Route::middleware('throttle:money-movement')->group(function (): void {
            // Пополнения счета
            Route::post('/deposit', 'deposit')->name('deposit');

            // Списания со счета
            Route::post('/withdraw', 'withdraw')->name('withdraw');

            // Перевод между счетами
            Route::post('/transfer', 'transfer')->name('transfer');

            // Повтор failed-транзакцию
            Route::post('/{uuid}/retry', 'retry')->whereUuid('uuid')->name('retry');
        });

        // Одна транзакция по uuid
        Route::get('/{uuid}', 'show')->whereUuid('uuid')->name('show');
    });
});

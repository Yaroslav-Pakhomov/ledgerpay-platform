<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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

    // Пополнения счета
    Route::post('/deposit', 'deposit')->name('deposit');

    // Списания со счета
    Route::post('/withdraw', 'withdraw')->name('withdraw');

    // Перевод между счетами
    Route::post('/transfer', 'transfer')->name('transfer');

    // Одна транзакция по uuid
    Route::get('/{uuid}', 'show')->whereUuid('uuid')->name('show');
});

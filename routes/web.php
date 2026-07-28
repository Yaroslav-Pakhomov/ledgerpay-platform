<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\TransactionController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin'       => Route::has('login'),
//         'canRegister'    => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion'     => PHP_VERSION,
//     ]);
// });

// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('guest')->group(function (): void {
    Route::controller(AuthController::class)->group(function (): void {
        // Страница входа
        Route::get('/login', 'loginPage')->name('login');
        // Отправка формы входа
        Route::post('/login', 'login')->name('login.store');

        // Страница регистрации
        Route::get('/register', 'registerPage')->name('register');
        // Отправка формы регистрации
        Route::post('/register', 'register')->name('register.store');
    });
});

Route::middleware('auth')->group(function (): void {
    // Главная страница клиента
    Route::get('/', DashboardController::class)->name('dashboard');

    // Выход из клиента
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Банковский счёт
    Route::controller(AccountController::class)->prefix('accounts')->name('accounts.')->group(function (): void {
        // Создание банковского счёта
        Route::post('/', 'store')->name('store');

        // Получение истории переводов по счёту
        Route::get('/{uuid}/ledger', 'ledger')->whereUuid('uuid')->name('ledger');
    });

    // Транзакции
    Route::controller(TransactionController::class)->prefix('transactions')->name('transactions.')->group(function (): void {
        // Вложение средств на счёт
        Route::post('/deposit', 'deposit')->name('deposit');

        // Вывод средств со счёта
        Route::post('/withdraw', 'withdraw')->name('withdraw');

        // Перевод средств со счёта на счёт
        Route::post('/transfer', 'transfer')->name('transfer');

        // Перевод средств со счёта на счёт
        Route::post('{uuid}/retry', 'retry')->whereUuid('uuid')->name('retry');
    });

    // Профиль Profile — оставить из Breeze (опционально)
    Route::controller(ProfileController::class)->prefix('profile')->name('profile.')->group(function (): void {

        // Страница редактирования профиля
        Route::get('/', 'edit')->name('edit');

        // Отправка формы профиля
        Route::patch('/', 'update')->name('update');

        // Удаление профиля
        Route::delete('/', 'destroy')->name('destroy');
    });
});

// Breeze password reset / email verification маршруты временно убираются (вернуть в отдельном коммите при необходимости).
// require __DIR__.'/auth.php';

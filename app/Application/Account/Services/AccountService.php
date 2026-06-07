<?php

declare(strict_types=1);

namespace App\Application\Account\Services;

use App\Application\Account\DTO\CreateAccountData;
use App\Domain\Account\Enums\AccountStatus;
use App\Domain\Account\Models\Account;
use App\Domain\Customer\Models\Customer;
use DomainException;

/**
 * Application service для работы со счетами.
 *
 * Инкапсулирует бизнес-операции Account domain:
 * - открытие счетов;
 * - проверки статуса клиента;
 * - инициализацию баланса.
 *
 * Сервис является orchestration layer
 * между HTTP/controllers и Domain models.
 *
 * В будущем здесь появятся:
 * - ограничения по валютам;
 * - лимиты;
 * - compliance-проверки;
 * - account policies.
 */
final class AccountService
{
    public function create(CreateAccountData $data): Account
    {
        $customer = Customer::query()->findOrFail($data->customerId);

        if (! $customer->isActive()) {
            throw new DomainException('Cannot open account for inactive customer.');
        }

        return Account::query()->create([
            'customer_id' => $customer->id,
            'currency' => strtoupper($data->currency),
            'balance' => 0,
            'status' => AccountStatus::Active,
        ]);
    }
}

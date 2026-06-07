<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Application\Customer\DTO\CreateCustomerData;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Models\Customer;

/**
 * Application service для работы с клиентами.
 *
 * Сервис инкапсулирует use cases,
 * связанные с Customer domain.
 *
 * На текущем этапе отвечает за:
 * - создание клиентов;
 * - установку начального статуса;
 * - изоляцию бизнес-логики от controllers.
 *
 * В дальнейшем здесь могут появиться:
 * - KYC/AML-проверки;
 * - блокировка клиента;
 * - lifecycle management;
 * - audit/event dispatching.
 */
final class CustomerService
{
    public function create(CreateCustomerData $createCustomerData): Customer
    {
        return Customer::query()->create([
            'name' => $createCustomerData->name,
            'email' => $createCustomerData->email,
            'status' => CustomerStatus::Active,
        ]);
    }
}

<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Domain\Account\Models{
/**
 * Доменная модель банковского счета.
 *
 * Счет принадлежит клиенту и хранит баланс в minor units,
 * например в копейках/центах, а не в рублях/долларах.
 *
 * @property AccountStatus $status
 * @property int $id остается внутренним ID
 * @property string $uuid UUID используем как публичный идентификатор
 * @property string $currency Код валюты
 * @property int $balance Баланс
 * @property int $customer_id ID Заказчика
 * @property \Illuminate\Support\Carbon $created_at Время создания
 * @property \Illuminate\Support\Carbon $updated_at Время обновления
 * @property-read \App\Domain\Customer\Models\Customer $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\Ledger\Models\LedgerEntry> $ledgerEntries
 * @property-read int|null $ledger_entries_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereUuid($value)
 */
	final class Account extends \Eloquent {}
}

namespace App\Domain\Customer\Models{
/**
 * Доменная модель клиента.
 *
 * Customer представляет владельца счетов в системе LedgerPay.
 *
 * Для внутренних связей используется числовой id,
 * а UUID выступает публичным идентификатором,
 * который безопасно отдавать во внешнее API.
 *
 * @property CustomerStatus $status
 * @property int $id остается внутренним ID
 * @property string $uuid UUID используем как публичный идентификатор
 * @property string $name Имя/ФИО
 * @property string $email Почта
 * @property \Illuminate\Support\Carbon $created_at Время создания
 * @property \Illuminate\Support\Carbon $updated_at Время обновления
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\Account\Models\Account> $accounts
 * @property-read int|null $accounts_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUuid($value)
 */
	final class Customer extends \Eloquent {}
}

namespace App\Domain\Ledger\Models{
/**
 * Запись бухгалтерского журнала (Ledger).
 *
 * Каждое движение средств по счету фиксируется отдельной записью:
 * - списание (Debit);
 * - зачисление (Credit).
 *
 * Ledger является источником финансовой истории системы.
 * Баланс счета может быть пересчитан на основе этих записей.
 *
 * Важное правило:
 * после создания запись не должна изменяться или удаляться.
 * Ошибки исправляются только созданием новых корректирующих записей.
 *
 * @property LedgerDirection $direction
 * @property-read Transaction $transaction
 * @property-read Account     $account
 * @property int $id остается внутренним ID
 * @property string $currency Код валюты
 * @property int $amount Сумма
 * @property int $balance_after Баланс после конкретной операции
 * @property int $transaction_id ID операции
 * @property int $account_id ID счета
 * @property \Illuminate\Support\Carbon $created_at Время создания
 * @property \Illuminate\Support\Carbon $updated_at Время обновления
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereUpdatedAt($value)
 */
	final class LedgerEntry extends \Eloquent {}
}

namespace App\Domain\Transaction\Models{
/**
 * Доменная модель финансовой транзакции.
 *
 * Transaction описывает намерение или факт движения средств:
 * - пополнение счета;
 * - списание со счета;
 * - перевод между счетами.
 *
 * Сама транзакция хранит общую информацию об операции,
 * а фактические движения по счетам фиксируются в ledger_entries.
 *
 * @property TransactionType $type
 * @property TransactionStatus $status
 * @property Carbon|null $processed_at
 * @property-read Account|null $sourceAccount
 * @property-read Account|null $targetAccount
 * @property int $id остается внутренним ID
 * @property string $uuid UUID используем как публичный идентификатор
 * @property string $currency Код валюты
 * @property string $idempotency_key Ключ идемпотентности
 * @property string|null $failure_reason Причина сбоя
 * @property int $amount Сумма
 * @property int|null $source_account_id ID Отправителя
 * @property int|null $target_account_id ID Получателя
 * @property \Illuminate\Support\Carbon $created_at Время создания
 * @property \Illuminate\Support\Carbon $updated_at Время обновления
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\Ledger\Models\LedgerEntry> $ledgerEntries
 * @property-read int|null $ledger_entries_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereSourceAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTargetAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUuid($value)
 */
	final class Transaction extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}


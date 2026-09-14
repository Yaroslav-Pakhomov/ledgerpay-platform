<?php

declare(strict_types=1);

namespace App\Http\Requests\Transaction\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Запрос на перевод средств между счетами через WEB.
 *
 * Этот класс принимает HTTP-запрос, проверяет его
 * и готовит данные для TransactionService.
 *
 * Здесь нет логики списания или зачисления денег —
 * только проверка входных данных.
 */
final class TransferRequest extends FormRequest
{
    /**
     * Разрешает ли текущий пользователь выполнить этот запрос.
     *
     * Сейчас доступ открыт для всех.
     * Позже здесь можно добавить проверку прав.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила проверки полей запроса.
     *
     * Здесь проверяем только формат данных:
     * uuid счетов, сумма, валюта.
     *
     * Также проверяем, что счета отправителя и получателя разные.
     *
     * Проверки вроде «счет активен» или «валюта совпадает»
     * выполняются позже в TransactionProcessorService.
     */
    public function rules(): array
    {
        return [
            'source_account_uuid' => ['required', 'uuid', 'exists:accounts,uuid'],
            'target_account_uuid' => [
                'required',
                'uuid',
                'exists:accounts,uuid',
                'different:source_account_uuid',
            ],
            'amount'   => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    /**
     * Возвращает Idempotency-Key.
     *
     * Если клиент повторит запрос с тем же ключом,
     * деньги не будут переведены второй раз.
     */
    public function idempotencyKey(): string
    {
        return 'web-transfer-' . Str::uuid()->toString();
    }
}

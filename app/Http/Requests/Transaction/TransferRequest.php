<?php

declare(strict_types=1);

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос на перевод средств между счетами через API.
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
     * Возвращает Idempotency-Key из заголовка запроса.
     *
     * Если клиент повторит запрос с тем же ключом,
     * деньги не будут переведены второй раз.
     */
    public function idempotencyKey(): string
    {
        return (string) $this->header('Idempotency-Key');
    }

    /**
     * Подготавливает данные перед проверкой.
     *
     * Убирает лишние пробелы в Idempotency-Key,
     * чтобы один и тот же ключ не считался разным.
     */
    protected function prepareForValidation(): void
    {
        $this->headers->set(
            'Idempotency-Key',
            trim($this->idempotencyKey())
        );
    }

    /**
     * Дополнительная проверка заголовков.
     *
     * В rules() нельзя проверить HTTP-заголовки,
     * поэтому наличие Idempotency-Key проверяем отдельно.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->idempotencyKey() === '') {
                $validator->errors()->add(
                    'Idempotency-Key',
                    'The Idempotency-Key header is required.'
                );
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Transaction\Backoffice;

use App\Domain\ReadModel\Models\TransactionSummaryReadModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransactionSummaryReadModel
 */
final class TransactionSummaryReadModelResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'uuid'                  => $this->transaction_uuid,
            'type'                  => $this->type,
            'status'                => $this->status,
            'amount'                => $this->amount,
            'currency'              => $this->currency,
            'source_account_uuid'   => $this->source_account_uuid,
            'target_account_uuid'   => $this->target_account_uuid,
            'source_customer_email' => $this->source_customer_email,
            'target_customer_email' => $this->target_customer_email,
            'failure_reason'        => $this->failure_reason,
            'created_at'            => $this->transaction_created_at->toDateTimeString(),
        ];
    }
}

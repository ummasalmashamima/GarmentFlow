<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use Illuminate\Validation\ValidationException;

final class ProcurementWorkflow
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CONVERTED_TO_PO = 'converted_to_po';

    public const SENT_TO_SUPPLIER = 'sent_to_supplier';

    public const PARTIALLY_RECEIVED = 'partially_received';

    public const FULLY_RECEIVED = 'fully_received';

    public const CLOSED = 'closed';

    public const CANCELLED = 'cancelled';

    public const RECEIVED = 'received';

    public const ACCEPTED = 'accepted';

    public const POSTED = 'posted';

    public function requisitionTransitions(): array
    {
        return [
            self::DRAFT => [self::SUBMITTED],
            self::SUBMITTED => [self::APPROVED, self::REJECTED],
            self::APPROVED => [self::CONVERTED_TO_PO],
        ];
    }

    public function orderTransitions(): array
    {
        return [
            self::DRAFT => [self::SUBMITTED, self::CANCELLED],
            self::SUBMITTED => [self::APPROVED, self::CANCELLED],
            self::APPROVED => [self::SENT_TO_SUPPLIER, self::CANCELLED],
            self::SENT_TO_SUPPLIER => [self::PARTIALLY_RECEIVED, self::FULLY_RECEIVED, self::CANCELLED],
            self::PARTIALLY_RECEIVED => [self::FULLY_RECEIVED, self::CLOSED],
            self::FULLY_RECEIVED => [self::CLOSED],
        ];
    }

    public function receiptTransitions(): array
    {
        return [
            self::DRAFT => [self::RECEIVED],
            self::RECEIVED => [self::ACCEPTED],
            self::ACCEPTED => [self::POSTED],
        ];
    }

    public function assertRequisitionTransition(PurchaseRequisition $requisition, string $newStatus): void
    {
        $this->assertAllowed($requisition->status, $newStatus, $this->requisitionTransitions(), 'Purchase Requisition');
    }

    public function assertOrderTransition(PurchaseOrder $order, string $newStatus): void
    {
        $this->assertAllowed($order->status, $newStatus, $this->orderTransitions(), 'Purchase Order');
    }

    public function assertReceiptTransition(GoodsReceipt $receipt, string $newStatus): void
    {
        $this->assertAllowed($receipt->status, $newStatus, $this->receiptTransitions(), 'Goods Receipt');
    }

    private function assertAllowed(string $current, string $new, array $transitions, string $document): void
    {
        if (! in_array($new, $transitions[$current] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "{$document} status cannot transition from {$current} to {$new}.",
            ]);
        }
    }
}

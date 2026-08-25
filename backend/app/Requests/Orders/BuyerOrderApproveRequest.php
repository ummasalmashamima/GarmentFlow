<?php

declare(strict_types=1);

namespace App\Requests\Orders;

class BuyerOrderApproveRequest extends BuyerOrderActionRequest
{
    protected function requiredPermission(): string
    {
        return 'buyer-order.approve';
    }
}

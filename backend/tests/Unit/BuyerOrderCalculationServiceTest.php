<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Orders\BuyerOrderCalculationService;
use PHPUnit\Framework\TestCase;

class BuyerOrderCalculationServiceTest extends TestCase
{
    public function test_it_calculates_line_and_order_totals(): void
    {
        $result = (new BuyerOrderCalculationService)->calculate([
            ['product_id' => 1, 'product_variant_id' => 1, 'quantity' => 1.5, 'unit_price' => 100],
            ['product_id' => 1, 'product_variant_id' => 2, 'quantity' => 2, 'unit_price' => 0],
        ]);

        $this->assertSame('3.5000', $result['total_quantity']);
        $this->assertSame('150.0000', $result['total_amount']);
        $this->assertSame('150.0000', $result['items'][0]['item_total']);
        $this->assertSame('0.0000', $result['items'][1]['item_total']);
    }

    public function test_it_applies_quantity_times_unit_price_without_frontend_logic(): void
    {
        $service = new BuyerOrderCalculationService;

        $this->assertSame(157.5, $service->lineTotal([
            'quantity' => 1.5,
            'unit_price' => 105,
        ]));
    }
}

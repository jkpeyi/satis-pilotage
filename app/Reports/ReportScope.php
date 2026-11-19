<?php

namespace App\Reports;

use App\Models\Customer;
use App\Models\Product;

/**
 * Perimeter of a report: a product, optionally narrowed to one customer.
 */
final readonly class ReportScope
{
    public function __construct(
        public int $productId,
        public ?int $customerId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            (int) $values['product_id'],
            isset($values['customer_id']) && $values['customer_id'] !== '' ? (int) $values['customer_id'] : null,
        );
    }

    /**
     * @return array{product_id: int, customer_id: ?int}
     */
    public function toArray(): array
    {
        return ['product_id' => $this->productId, 'customer_id' => $this->customerId];
    }

    public function hash(): string
    {
        return hash('sha256', (string) json_encode($this->toArray()));
    }

    public function product(): Product
    {
        return Product::query()->findOrFail($this->productId);
    }

    public function customer(): ?Customer
    {
        return $this->customerId !== null ? Customer::query()->find($this->customerId) : null;
    }

    public function label(): string
    {
        $customer = $this->customer();

        return $this->product()->name.($customer !== null ? ' · '.$customer->name : '');
    }
}

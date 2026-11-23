<?php

namespace App\Reports;

use App\Models\Customer;
use App\Models\DiagnosticCampaign;
use App\Models\Product;
use App\Models\RefactoringStrategy;

/**
 * Perimeter of a report: a product, optionally narrowed to one customer.
 */
final readonly class ReportScope
{
    public function __construct(
        public int $productId,
        public ?int $customerId = null,
        // L4 is generated per diagnostic campaign (one repository at one commit).
        public ?int $campaignId = null,
        // L7 is generated per refactoring strategy.
        public ?int $strategyId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            (int) $values['product_id'],
            isset($values['customer_id']) && $values['customer_id'] !== '' ? (int) $values['customer_id'] : null,
            isset($values['campaign_id']) && $values['campaign_id'] !== '' ? (int) $values['campaign_id'] : null,
            isset($values['strategy_id']) && $values['strategy_id'] !== '' ? (int) $values['strategy_id'] : null,
        );
    }

    /**
     * The campaign appears only when set, so that the scopes (and version numbers) of the other
     * deliverables stay unchanged.
     *
     * @return array{product_id: int, customer_id: ?int, campaign_id?: int, strategy_id?: int}
     */
    public function toArray(): array
    {
        return ['product_id' => $this->productId, 'customer_id' => $this->customerId]
            + ($this->campaignId !== null ? ['campaign_id' => $this->campaignId] : [])
            + ($this->strategyId !== null ? ['strategy_id' => $this->strategyId] : []);
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

    public function campaign(): ?DiagnosticCampaign
    {
        return $this->campaignId !== null ? DiagnosticCampaign::query()->with('repository.version.customer')->find($this->campaignId) : null;
    }

    public function strategy(): ?RefactoringStrategy
    {
        return $this->strategyId !== null ? RefactoringStrategy::query()->find($this->strategyId) : null;
    }

    public function label(): string
    {
        $customer = $this->customer();
        $campaign = $this->campaign();
        $strategy = $this->strategy();

        return $this->product()->name.($customer !== null ? ' · '.$customer->name : '')
            .($campaign !== null ? ' · '.$campaign->displayCode().' '.$campaign->repository->path.' @ '.$campaign->shortCommit() : '')
            .($strategy !== null ? ' · '.$strategy->displayCode().' '.$strategy->title : '');
    }
}

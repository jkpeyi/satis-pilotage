<?php

namespace App\Livewire;

use App\Enums\IssueStatus;
use App\Models\Customer;
use App\Models\Issue;
use App\Models\Product;
use App\Models\Version;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tableau de bord')]
class Dashboard extends Component
{
    /**
     * @return Builder<Issue>
     */
    private function openIssues(): Builder
    {
        return Issue::query()->whereNull('archived_at')->whereNotIn('status', [IssueStatus::Fixed->value, IssueStatus::Dropped->value]);
    }

    public function render(): View
    {
        return view('livewire.dashboard', [
            'counts' => [
                'Produits' => Product::query()->active()->count(),
                'Clients' => Customer::query()->active()->count(),
                'Versions' => Version::query()->active()->count(),
                'Problèmes non résolus' => $this->openIssues()->count(),
            ],
            'openEffort' => (float) $this->openIssues()->sum('effort_days'),
        ]);
    }
}

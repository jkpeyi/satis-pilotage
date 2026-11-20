<?php

namespace App\Livewire;

use App\Enums\IssueStatus;
use App\Enums\VersionStatus;
use App\Models\Customer;
use App\Models\Issue;
use App\Models\Product;
use App\Models\Version;
use App\Support\Effort;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
        return Issue::query()->whereNull('archived_at')->whereIn('status', IssueStatus::openValues());
    }

    public function render(): View
    {
        return view('livewire.dashboard', [
            'counts' => [
                'Produits' => Product::query()->active()->count(),
                'Clients' => Customer::query()->active()->count(),
                // A future perspective (e.g. Satis_SaaS) and a retired version are not in service (SPEC-001, scenario 9).
                'Versions en service' => Version::query()->active()
                    ->whereIn('status', [VersionStatus::InProduction->value, VersionStatus::InTesting->value])
                    ->count(),
                'Problèmes non résolus' => $this->openIssues()->count(),
            ],
            'openEffort' => Effort::format(
                $this->openIssues()->sum('effort_min_days'),
                $this->openIssues()->sum(DB::raw('COALESCE(effort_max_days, effort_min_days)')),
            ) ?? '0 j/h',
        ]);
    }
}

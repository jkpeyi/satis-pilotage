<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Version;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tableau de bord')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.dashboard', [
            'counts' => [
                'Produits' => Product::query()->active()->count(),
                'Clients' => Customer::query()->active()->count(),
                'Versions' => Version::query()->active()->count(),
            ],
        ]);
    }
}

<?php

namespace App\Livewire\Versions;

use App\Actions\Referential\SaveVersion;
use App\Enums\VerificationStatus;
use App\Enums\VersionStatus;
use App\Livewire\Concerns\EnumOptions;
use App\Livewire\Forms\VersionForm;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Models\Version;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Form extends Component
{
    use EnumOptions;

    public VersionForm $form;

    public function mount(?Version $version = null): void
    {
        if ($version?->exists) {
            $this->form->setVersion($version);

            return;
        }

        $this->form->product_id = (string) request()->integer('product') ?: '';
        $this->form->customer_id = (string) request()->integer('customer') ?: '';
    }

    public function save(SaveVersion $saveVersion): void
    {
        $version = $saveVersion->handle($this->form->data(), $this->form->version);

        session()->flash('status', 'Version enregistrée.');
        $this->redirectRoute('versions.show', $version, navigate: true);
    }

    public function render(): View
    {
        $title = $this->form->version ? 'Modifier '.$this->form->version->name : 'Nouvelle version';

        return view('livewire.versions.form', [
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
            'customers' => Customer::query()->whereNull('archived_at')->orderBy('name')->pluck('name', 'id'),
            'statuses' => $this->enumOptions(VersionStatus::class),
            'verificationStatuses' => $this->enumOptions(VerificationStatus::class),
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
        ])->title($title);
    }
}

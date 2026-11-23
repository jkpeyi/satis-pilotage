<?php

namespace App\Livewire\Customers;

use App\Actions\Referential\ArchiveRecord;
use App\Actions\Referential\SaveCustomer;
use App\Enums\CustomerType;
use App\Livewire\Concerns\EnumOptions;
use App\Livewire\Forms\CustomerForm;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Form extends Component
{
    use EnumOptions;

    public CustomerForm $form;

    public function mount(?Customer $customer = null): void
    {
        if ($customer?->exists) {
            $this->form->setCustomer($customer);
        }
    }

    public function save(SaveCustomer $saveCustomer): void
    {
        $saveCustomer->handle($this->form->data(), $this->form->customer);

        session()->flash('status', 'Client enregistré.');
        $this->redirectRoute('customers.index', navigate: true);
    }

    public function toggleArchive(ArchiveRecord $archiveRecord): void
    {
        $customer = $this->form->customer;

        if ($customer === null) {
            return;
        }

        $archiveRecord->handle($customer, ! $customer->isArchived());
        session()->flash('status', $customer->isArchived() ? 'Client archivé.' : 'Client restauré.');
        $this->redirectRoute('customers.index', navigate: true);
    }

    public function render(): View
    {
        $title = $this->form->customer ? 'Modifier '.$this->form->customer->name : 'Nouveau client';

        return view('livewire.customers.form', ['types' => $this->enumOptions(CustomerType::class)])->title($title);
    }
}

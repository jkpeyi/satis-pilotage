<?php

namespace App\Livewire\Products;

use App\Actions\Referential\ArchiveRecord;
use App\Actions\Referential\SetReferenceVersion;
use App\Models\Product;
use App\Models\Version;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public Product $product;

    public function setReference(int $versionId, SetReferenceVersion $setReferenceVersion): void
    {
        try {
            $setReferenceVersion->handle($this->product, Version::query()->findOrFail($versionId));
        } catch (DomainException $exception) {
            $this->addError('reference', $exception->getMessage());

            return;
        }

        session()->flash('status', 'Version de référence mise à jour.');
        $this->redirectRoute('products.show', $this->product, navigate: true);
    }

    public function clearReference(SetReferenceVersion $setReferenceVersion): void
    {
        $setReferenceVersion->handle($this->product, null);
        $this->product->refresh();
    }

    public function toggleArchive(ArchiveRecord $archiveRecord): void
    {
        $archiveRecord->handle($this->product, ! $this->product->isArchived());
        session()->flash('status', $this->product->isArchived() ? 'Produit archivé.' : 'Produit restauré.');
        $this->redirectRoute('products.show', $this->product, navigate: true);
    }

    public function render(): View
    {
        $versions = $this->product->versions()
            ->with('customer')
            ->withCount(['repositories' => fn ($query) => $query->whereNull('archived_at')])
            ->orderByRaw('archived_at IS NOT NULL')
            ->orderBy('name')
            ->get();

        return view('livewire.products.show', ['versions' => $versions])->title($this->product->name);
    }
}

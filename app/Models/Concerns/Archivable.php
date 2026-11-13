<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Records are archived through archived_at instead of being deleted (RG-12).
 *
 * @mixin Model
 */
trait Archivable
{
    public function initializeArchivable(): void
    {
        $this->mergeCasts(['archived_at' => 'datetime']);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn('archived_at'));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull($this->qualifyColumn('archived_at'));
    }

    public function isArchived(): bool
    {
        return $this->getAttribute('archived_at') !== null;
    }

    public function archive(): void
    {
        $this->setAttribute('archived_at', now());
        $this->save();
    }

    public function unarchive(): void
    {
        $this->setAttribute('archived_at', null);
        $this->save();
    }
}

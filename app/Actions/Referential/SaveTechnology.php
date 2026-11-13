<?php

namespace App\Actions\Referential;

use App\Models\Technology;

final class SaveTechnology
{
    /**
     * @param  array{component: string, cycle: string, end_of_support: ?string}  $data
     */
    public function handle(array $data, ?Technology $technology = null): Technology
    {
        $technology ??= new Technology;
        $data['component'] = strtolower(trim($data['component']));
        $data['cycle'] = trim($data['cycle']);

        $technology->fill($data)->save();

        return $technology;
    }
}

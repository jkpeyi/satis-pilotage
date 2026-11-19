<?php

namespace App\Actions\Referential;

use App\Models\Release;

final class SaveRelease
{
    /**
     * @param  array{repository_id: int, name: string, commit_sha: ?string, released_on: ?string, notes: ?string}  $data
     */
    public function handle(array $data, ?Release $release = null): Release
    {
        $release ??= new Release;
        $release->fill($data)->save();

        return $release;
    }
}

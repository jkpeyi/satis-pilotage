<?php

namespace App\Console\Commands;

use App\Actions\Referential\SyncTechnologiesFromEstate;
use Illuminate\Console\Command;

class SyncTechnologies extends Command
{
    protected $signature = 'technologies:sync';

    protected $description = 'Ajouter au référentiel des technologies celles déjà saisies sur les dépôts, composants et environnements';

    public function handle(SyncTechnologiesFromEstate $syncTechnologies): int
    {
        $added = $syncTechnologies->handle();
        $this->info("{$added} cycle(s) ajouté(s). Renseignez leur date de fin de support dans l'écran Technologies.");

        return self::SUCCESS;
    }
}

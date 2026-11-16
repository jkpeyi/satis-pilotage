<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

class ResetUserPassword extends Command
{
    protected $signature = 'user:password {email} {--password= : Avoid in shell history; prompted when omitted}';

    protected $description = "Changer le mot de passe d'un compte local";

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Aucun compte avec cette adresse e-mail.');

            return self::FAILURE;
        }

        $newPassword = $this->option('password') ?: password('Nouveau mot de passe (12 caractères minimum)', required: true);

        $validator = Validator::make(['password' => $newPassword], ['password' => ['required', 'string', 'min:12']]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user->update(['password' => $newPassword]);

        $this->info("Mot de passe mis à jour pour {$user->email}.");

        return self::SUCCESS;
    }
}

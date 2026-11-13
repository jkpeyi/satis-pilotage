<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Phase 1 has no registration screen: local accounts are created from the command line.
 */
class CreateUser extends Command
{
    protected $signature = 'user:create {--name=} {--email=} {--password= : Avoid in shell history; prompted when omitted}';

    protected $description = 'Créer un compte utilisateur local';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: text('Nom', required: true),
            'email' => $this->option('email') ?: text('Adresse e-mail', required: true),
            'password' => $this->option('password') ?: password('Mot de passe (12 caractères minimum)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create($validator->validated());

        $this->info("Compte créé pour {$user->email}.");

        return self::SUCCESS;
    }
}

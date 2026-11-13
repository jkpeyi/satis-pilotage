<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="mb-6 text-lg font-semibold text-slate-900">Connexion</h1>

    <form wire:submit="login" class="space-y-4">
        <x-ui.input label="Adresse e-mail" type="email" wire:model="email" autocomplete="username" autofocus required />
        <x-ui.input label="Mot de passe" type="password" wire:model="password" autocomplete="current-password" required />

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" wire:model="remember" class="rounded border-slate-300 text-brand-700">
            Rester connecté
        </label>

        <x-ui.button type="submit" class="w-full">
            <span wire:loading.remove wire:target="login">Se connecter</span>
            <span wire:loading wire:target="login">Connexion…</span>
        </x-ui.button>
    </form>

    <p class="mt-6 text-xs text-slate-500">Pas de compte ? Demandez-en un à l'équipe DTI.</p>
</div>

@if (session('status'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
         class="mb-6 flex items-start justify-between gap-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
         role="status">
        <span>{{ session('status') }}</span>
        <button type="button" x-on:click="show = false" class="text-emerald-700 hover:text-emerald-900" aria-label="Fermer">×</button>
    </div>
@endif

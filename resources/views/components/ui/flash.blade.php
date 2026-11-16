{{-- Shows the session status after a redirect, and `notify` events dispatched by Livewire actions without redirect. --}}
<div x-data="{ message: @js(session('status')), timer: null,
               show(text) { this.message = text; clearTimeout(this.timer); this.timer = setTimeout(() => this.message = null, 5000) } }"
     x-init="if (message) show(message)"
     x-on:notify.window="show($event.detail.message)">
    <div x-show="message" x-cloak x-transition.opacity
         class="mb-6 flex items-start justify-between gap-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
         role="status">
        <span x-text="message"></span>
        <button type="button" x-on:click="message = null" class="text-emerald-700 hover:text-emerald-900" aria-label="Fermer">×</button>
    </div>
</div>

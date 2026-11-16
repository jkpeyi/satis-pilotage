@props(['code', 'start' => 1])

{{-- Read-only code with line numbers starting at $start. No highlighting library: plain, escaped text. --}}
<pre {{ $attributes->class('code-lines overflow-x-auto rounded-md bg-slate-900 py-3 text-xs leading-5 text-slate-100') }} style="counter-reset: line {{ max(0, (int) $start - 1) }}"><code>@foreach (preg_split('/\R/', (string) $code) as $line)<span>{{ $line }}</span>@endforeach</code></pre>

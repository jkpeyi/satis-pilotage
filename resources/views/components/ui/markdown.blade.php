@props(['text'])

{{-- Renders user Markdown in safe mode: raw HTML is escaped and unsafe links are dropped. --}}
<div {{ $attributes->class('markdown text-sm text-slate-700') }}>
    {!! Str::markdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
</div>

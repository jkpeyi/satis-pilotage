@props(['record'])

{{-- Creator, last editor and dates of a record (EF-604). --}}
<p {{ $attributes->class('text-xs text-slate-500') }}>
    Créé le {{ $record->created_at?->format('d/m/Y à H:i') }}@if ($record->creator) par {{ $record->creator->name }}@endif.
    @if ($record->updated_at && $record->updated_at->ne($record->created_at))
        Modifié le {{ $record->updated_at->format('d/m/Y à H:i') }}@if ($record->editor) par {{ $record->editor->name }}@endif.
    @endif
</p>

@props(['label' => null, 'hint' => null, 'required' => false, 'type' => 'text'])

@php
    $model = $attributes->wire('model')->value();
    $id = $attributes->get('id', $model ? str_replace('.', '-', $model) : null);
@endphp

<x-ui.field :label="$label" :for="$id" :error="$model" :hint="$hint" :required="$required">
    <input type="{{ $type }}" id="{{ $id }}"
        {{ $attributes->class([
            'block w-full rounded-md border bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-600/30',
            'border-red-400' => $model && $errors->has($model),
            'border-slate-300 focus:border-brand-600' => ! ($model && $errors->has($model)),
        ]) }}>
</x-ui.field>

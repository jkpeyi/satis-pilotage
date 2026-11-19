@props(['label' => null, 'hint' => null, 'required' => false, 'placeholder' => null, 'options' => []])

{{-- options: value => label --}}
@php
    $model = $attributes->wire('model')->value();
    $id = $attributes->get('id', $model ? str_replace('.', '-', $model) : null);
@endphp

<x-ui.field :label="$label" :for="$id" :error="$model" :hint="$hint" :required="$required">
    <select id="{{ $id }}"
        {{ $attributes->class([
            'block w-full rounded-md border bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-600/30 disabled:bg-slate-50 disabled:text-slate-500',
            'border-red-400' => $model && $errors->has($model),
            'border-slate-300 focus:border-brand-600' => ! ($model && $errors->has($model)),
        ]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}">{{ $optionLabel }}</option>
        @endforeach
        {{ $slot }}
    </select>
</x-ui.field>

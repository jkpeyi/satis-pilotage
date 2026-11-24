@props(['layout', 'colors' => []])

{{-- Workflow diagram (RG-17) from WorkflowGraph::layout(): actions in boxes, decisions in diamonds, ends in pills.
     colors (step id => green, amber, red, gray) tints the steps by their coverage in a version. --}}
@php
    $palette = ['green' => ['#ecfdf5', '#059669'], 'amber' => ['#fffbeb', '#d97706'], 'red' => ['#fef2f2', '#dc2626'], 'gray' => ['#f8fafc', '#94a3b8']];
@endphp
@if ($layout['nodes'] !== [])
    <div {{ $attributes->class('overflow-x-auto') }}>
        <svg xmlns="http://www.w3.org/2000/svg" width="{{ $layout['width'] }}" height="{{ $layout['height'] }}"
            viewBox="0 0 {{ $layout['width'] }} {{ $layout['height'] }}" role="img" aria-label="Schéma du workflow" class="max-w-none font-sans">
            <defs>
                <marker id="wf-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                    <path d="M 0 0 L 10 5 L 0 10 z" fill="#64748b" />
                </marker>
            </defs>

            @foreach ($layout['edges'] as $edge)
                <polyline points="{{ collect($edge['points'])->map(fn ($point) => $point[0].','.$point[1])->implode(' ') }}"
                    fill="none" stroke="#64748b" stroke-width="1.5" @if ($edge['loop']) stroke-dasharray="5 4" @endif marker-end="url(#wf-arrow)" />
                @if ($edge['label'])
                    <text x="{{ $edge['labelAt'][0] }}" y="{{ $edge['labelAt'][1] }}" font-size="11" font-weight="600" fill="#92400e">{{ $edge['label'] }}</text>
                @endif
            @endforeach

            @foreach ($layout['nodes'] as $node)
                @php
                    $cx = $node['x'] + $node['w'] / 2;
                    $cy = $node['y'] + $node['h'] / 2;
                    $firstLine = $cy - (count($node['lines']) - 1) * 7;
                    $tint = $palette[$colors[$node['id']] ?? ''] ?? null;
                @endphp
                <g>
                    @if ($node['kind'] === 'decision')
                        <polygon points="{{ $cx }},{{ $node['y'] }} {{ $node['x'] + $node['w'] }},{{ $cy }} {{ $cx }},{{ $node['y'] + $node['h'] }} {{ $node['x'] }},{{ $cy }}"
                            fill="#fffbeb" stroke="#d97706" stroke-width="1.5" />
                    @elseif ($node['kind'] === 'end')
                        <rect x="{{ $node['x'] }}" y="{{ $node['y'] }}" width="{{ $node['w'] }}" height="{{ $node['h'] }}" rx="{{ $node['h'] / 2 }}"
                            fill="#f1f5f9" stroke="#334155" stroke-width="2" />
                    @else
                        <rect x="{{ $node['x'] }}" y="{{ $node['y'] }}" width="{{ $node['w'] }}" height="{{ $node['h'] }}" rx="6"
                            fill="{{ $tint[0] ?? '#ffffff' }}" stroke="{{ $tint[1] ?? '#94a3b8' }}" stroke-width="{{ $tint ? 2.5 : 1.5 }}"
                            @if (($colors[$node['id']] ?? '') === 'gray') stroke-dasharray="4 3" @endif />
                    @endif
                    @foreach ($node['lines'] as $index => $line)
                        <text x="{{ $cx }}" y="{{ $firstLine + $index * 14 + 4 }}" text-anchor="middle" font-size="12" fill="#0f172a">{{ $line }}</text>
                    @endforeach
                </g>
            @endforeach
        </svg>
    </div>
@endif

@props(['color' => 'gray'])

@php
    $colors = [
        'gray' => 'background-color: #f3f4f6; color: #374151; border: 1px solid #d1d5db;',
        'green' => 'background-color: #d1fae5; color: #065f46; border: 1px solid #6ee7b7;',
        'red' => 'background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;',
        'blue' => 'background-color: #dbeafe; color: #1e40af; border: 1px solid #93c5fd;',
    ];
    $selectedStyle = $colors[$color] ?? $colors['gray'];
@endphp

<span style="padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; display: inline-block; margin: 2px; {{ $selectedStyle }}">
    {{ $slot }}
</span>
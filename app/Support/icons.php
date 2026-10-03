<?php

declare(strict_types=1);

function icon(string $name): string
{
    $paths = [
        'film' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M7 3v18M17 3v18M3 8h4m-4 8h4m10-8h4m-4 8h4M7 12h10"/>',
        'news' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h4v4H7zM15 7h2m-2 4h2M7 15h10M7 18h10"/>',
        'play' => '<path d="m9 5 11 7-11 7z"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 11h18m-14 5h2m4 0h2"/>',
        'ticket' => '<path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4zM15 5v2m0 3v2m0 3v4"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'building' => '<path d="M4 21V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v16M2 21h20M9 21v-5h6v5M8 7h1m6 0h1M8 11h1m6 0h1"/>',
        'chart' => '<path d="M3 3v18h18M8 16v-5m5 5V7m5 9v-3"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 5a3 3 0 0 1 0 6m4 10v-3a6 6 0 0 0-4-5"/>',
        'cup' => '<path d="M5 8h14l-2 13H7zM4 8h16M9 8l3-5h4"/>',
        'wallet' => '<path d="M20 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2v-3M3 7h15M16 10h5v6h-5a3 3 0 0 1 0-6z"/>',
        'archive' => '<rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v13h14V8M10 12h4"/>',
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'chevron-left' => '<path d="m15 5-7 7 7 7"/>',
        'chevron-right' => '<path d="m9 5 7 7-7 7"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'search' => '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'check' => '<path d="m4 12 5 5L20 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['ticket']) . '</svg>';
}

@props([
    // "short"  -> © 2025 RSPI Prof. Dr. Sulianti Saroso
    // "full"   -> © 2025 Rumah Sakit Penyakit Infeksi Prof. Dr. Sulianti Saroso. Hak Cipta Dilindungi Undang-Undang.
    'variant' => 'short',
])

@php
    // Tahun pertama publikasi; ditampilkan sebagai rentang bila sudah lewat tahun.
    $mulai = (int) config('app.copyright_year');
    $sekarang = (int) now()->format('Y');
    $tahun = $mulai >= $sekarang ? $sekarang : "{$mulai}–{$sekarang}";

    $pemilik = $variant === 'full' ? config('app.owner_full') : config('app.owner');
@endphp

<div {{ $attributes->class(['text-xs opacity-60 text-center']) }}>
    <p>&copy; {{ $tahun }} {{ $pemilik }}.</p>
</div>

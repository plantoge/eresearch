<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    {{-- Terapkan tema tersimpan SEBELUM render agar tidak berkedip.
         Dipasang ulang di livewire:navigated karena wire:navigate
         men-swap dokumen tanpa menjalankan ulang script head. --}}
    <script>
        window.applyTheme = function () {
            const t = localStorage.getItem('theme');
            if (t) document.documentElement.setAttribute('data-theme', t);
        };
        window.applyTheme();
        document.addEventListener('livewire:navigated', window.applyTheme);
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200/50">

<x-mary-nav sticky full-width>
    <x-slot:brand>
        <label for="main-drawer" class="lg:hidden mr-3">
            <x-mary-icon name="o-bars-3" class="cursor-pointer" />
        </label>
        <div class="font-bold text-lg">eResearch <span class="text-primary">Proposal</span></div>
    </x-slot:brand>
    <x-slot:actions>
        {{-- Pilihan tema daisyUI --}}
        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-ghost btn-sm">
                <x-mary-icon name="o-swatch" class="w-4 h-4" />
                <span class="hidden sm:inline">Tema</span>
            </div>
            <ul tabindex="0" class="dropdown-content menu bg-base-200 rounded-box z-50 w-44 p-2 shadow-lg">
                @foreach (['light' => 'Light', 'dark' => 'Dark', 'cupcake' => 'Cupcake', 'corporate' => 'Corporate', 'emerald' => 'Emerald', 'nord' => 'Nord', 'winter' => 'Winter', 'night' => 'Night', 'dracula' => 'Dracula', 'retro' => 'Retro'] as $t => $label)
                    <li>
                        <a onclick="document.documentElement.setAttribute('data-theme', '{{ $t }}'); localStorage.setItem('theme', '{{ $t }}'); document.activeElement.blur()"
                           class="flex items-center justify-between">
                            {{ $label }}
                            {{-- pratinjau warna tema --}}
                            <span data-theme="{{ $t }}" class="flex gap-0.5 rounded bg-base-100 border border-base-300 p-0.5">
                                <span class="w-1.5 h-3 rounded-xs bg-primary"></span>
                                <span class="w-1.5 h-3 rounded-xs bg-secondary"></span>
                                <span class="w-1.5 h-3 rounded-xs bg-accent"></span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        @auth
            <x-mary-dropdown right>
                <x-slot:trigger>
                    <x-mary-button :label="auth()->user()->name" icon="o-user-circle" class="btn-ghost btn-sm" />
                </x-slot:trigger>
                <x-mary-menu-item title="{{ auth()->user()->getRoleNames()->implode(', ') }}" icon="o-identification" />
                <x-mary-menu-item title="Profil" icon="o-user-circle" link="{{ route('profile') }}" />
                <x-mary-menu-item title="Keluar" icon="o-arrow-right-start-on-rectangle"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();" />
            </x-mary-dropdown>
            <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
        @endauth
    </x-slot:actions>
</x-mary-nav>

<x-mary-main full-width with-nav>
    <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100">
        <livewire:layout.sidebar />
    </x-slot:sidebar>
    {{-- Notis hak cipta ditaruh di dalam slot content, bukan slot footer milik
         x-mary-main: slot footer dirender di luar drawer sehingga menambah
         tinggi halaman dan menyisakan ruang kosong di bawah sidebar.
         Tinggi konten dikunci setinggi viewport dikurangi navbar (65px, angka
         yang sama dipakai x-mary-main untuk sidebar) lalu isi halaman dibungkus
         flex-1 agar notis selalu menempel di dasar layar meski halaman pendek. --}}
    <x-slot:content class="min-h-[calc(100vh-65px)] flex flex-col">
        <div class="flex-1">
            {{ $slot }}
        </div>

        <x-copyright class="mt-8 pt-4 border-t border-base-300" />
    </x-slot:content>
</x-mary-main>

<x-mary-toast />
</body>
</html>

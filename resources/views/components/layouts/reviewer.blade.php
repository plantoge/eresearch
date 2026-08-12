<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
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
{{-- text-lg (18px) sebagai ukuran dasar seluruh halaman, bukan text-base:
     penggunanya dokter senior, PRD §22 menetapkan body 16–18px. --}}
<body class="min-h-screen font-sans antialiased bg-base-200/40 text-lg">

{{-- Navigasi minimal (PRD §4.5): nama aplikasi, identitas reviewer, pemilih
     tema, Keluar. Sengaja TANPA sidebar dan tanpa dropdown profil — halaman
     kerja reviewer tidak boleh menawarkan jalan keluar dari alurnya.
     Ukuran kontrol disamakan 48px mengikuti PRD §22. --}}
<header class="bg-base-100 border-b border-base-300 sticky top-0 z-30">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
        <div class="font-bold text-xl sm:text-2xl">
            eResearch <span class="text-primary">Proposal</span>
        </div>

        @auth
            <div class="flex items-center gap-2 sm:gap-4">
                <span class="hidden md:inline text-base opacity-70">{{ auth()->user()->name }}</span>

                {{-- Pilihan tema daisyUI — mekanismenya sama dengan layout utama
                     (localStorage + data-theme), hanya ukurannya diperbesar. --}}
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button"
                        class="btn btn-ghost min-h-[48px] h-[48px] px-4 text-base normal-case">
                        <x-mary-icon name="o-swatch" class="w-5 h-5" />
                        <span class="hidden sm:inline">Tema</span>
                    </div>
                    <ul tabindex="0"
                        class="dropdown-content menu bg-base-200 rounded-box z-50 w-56 p-2 shadow-lg max-h-[70vh] overflow-y-auto">
                        @foreach (['light' => 'Terang', 'dark' => 'Gelap', 'cupcake' => 'Cupcake', 'corporate' => 'Corporate', 'emerald' => 'Emerald', 'nord' => 'Nord', 'winter' => 'Winter', 'night' => 'Night', 'dracula' => 'Dracula', 'retro' => 'Retro'] as $t => $label)
                            <li>
                                <a onclick="document.documentElement.setAttribute('data-theme', '{{ $t }}'); localStorage.setItem('theme', '{{ $t }}'); document.activeElement.blur()"
                                    class="flex items-center justify-between text-base py-3">
                                    {{ $label }}
                                    {{-- pratinjau warna tema --}}
                                    <span data-theme="{{ $t }}"
                                        class="flex gap-0.5 rounded bg-base-100 border border-base-300 p-1">
                                        <span class="w-2 h-4 rounded-xs bg-primary"></span>
                                        <span class="w-2 h-4 rounded-xs bg-secondary"></span>
                                        <span class="w-2 h-4 rounded-xs bg-accent"></span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="btn btn-outline min-h-[48px] h-[48px] px-5 text-base normal-case">
                        <x-mary-icon name="o-arrow-right-start-on-rectangle" class="w-5 h-5" />
                        <span class="hidden sm:inline">Keluar</span>
                    </button>
                </form>
            </div>
        @endauth
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
    {{ $slot }}

    <x-copyright class="mt-12 pt-6 border-t border-base-300" />
</main>

<x-mary-toast />
</body>
</html>

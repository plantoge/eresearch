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
<body class="min-h-screen font-sans antialiased bg-base-200 flex flex-col items-center p-4">
    {{-- flex-1 menyerap sisa tinggi layar agar kartu tetap terpusat secara
         vertikal sementara footer menempel di dasar layar. --}}
    <div class="w-full max-w-md flex-1 flex flex-col justify-center">
        <div class="text-center mb-6">
            <div class="font-bold text-2xl">eResearch <span class="text-primary">Proposal</span></div>
            <p class="text-sm opacity-60">Pengajuan & Review Proposal Penelitian</p>
        </div>
        {{ $slot }}
    </div>

    <footer class="w-full max-w-md pt-6">
        <x-copyright variant="full" />
    </footer>
    <x-mary-toast />
</body>
</html>

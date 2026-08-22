{{--
    Blok tanda tangan peneliti utama — dipakai formulir etik dan informed consent.

    Nama & tanggal tidak diminta lagi: nama penanda tangan adalah peneliti utama
    proposal ini, dan tanggalnya adalah saat formulir dikirim. Meminta keduanya
    diketik ulang hanya membuka peluang isian yang berbeda dari datanya sendiri.

    $model = jalur wire:model (mis. 'formEtik.tanda_tangan')
    $nilai = isi tanda tangan yang sudah tersimpan, untuk pratinjau
--}}
<div>
    <div class="font-semibold text-sm mb-1">Tanda Tangan Peneliti Utama</div>
    <div class="text-xs opacity-60 mb-2">
        {{ $proposal->peneliti_utama }} · tanggal terisi otomatis saat dikirim
    </div>

    @if ($nilai)
        {{-- Kanvas kosong saat dibuka ulang akan terlihat seperti tanda tangan
             yang hilang, padahal tersimpan. Pratinjau ini yang memberi tahu bahwa
             ia masih ada, dan menggambar ulang akan menggantikannya. --}}
        <div class="mb-2">
            <div class="text-xs opacity-60 mb-1">Tanda tangan tersimpan:</div>
            <img src="{{ $nilai }}" alt="Tanda tangan tersimpan"
                class="border border-base-300 rounded-lg bg-white max-h-24">
        </div>

        {{-- Browser membaca PNG dengan baik, jadi pratinjau di atas tampak
             baik-baik saja sekalipun berkas itu TIDAK akan tercetak. Tanpa
             peringatan ini, peneliti tidak punya satu pun alasan untuk menggambar
             ulang — dan baru tahu setelah PDF-nya keluar kosong. --}}
        @unless (\App\Support\TandaTangan::adalahSvg($nilai))
            <x-mary-alert icon="o-exclamation-triangle" class="alert-warning mb-2 text-sm">
                Tanda tangan ini tersimpan dalam format lama dan <strong>tidak dapat dicetak</strong> ke PDF.
                Gambar ulang di kotak di bawah, lalu simpan.
            </x-mary-alert>
        @endunless
    @endif

    <x-tanda-tangan :model="$model" />
</div>

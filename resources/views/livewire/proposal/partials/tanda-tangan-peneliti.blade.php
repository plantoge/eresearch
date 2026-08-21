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
    @endif

    {{-- `id` WAJIB dan diturunkan dari jalur wire:model.

         x-mary-signature merakit id kanvasnya dari `md5(serialize($this))` atas
         argumen KONSTRUKTOR saja — `wire:model` tidak ikut, karena bag atribut
         baru dipasang Blade setelah konstruksi. Dua pemanggilan dengan height dan
         hint yang sama karena itu menghasilkan id yang sama persis, lalu
         `document.getElementById()` pada kanvas kedua menemukan kanvas PERTAMA:
         kanvas kedua tidak pernah dipasangi SignaturePad dan diam saat digambar.

         Diturunkan dari $model, bukan diminta sebagai parameter terpisah, supaya
         pemanggil berikutnya tidak bisa lupa mengisinya — jalur model sudah
         dijamin unik per formulir. --}}
    <x-mary-signature wire:model="{{ $model }}" :id="str_replace('.', '-', $model)" height="180"
        clear-text="Hapus"
        hint="Gambar tanda tangan Anda di dalam kotak. Bisa memakai mouse, pena, atau jari di layar sentuh." />
</div>

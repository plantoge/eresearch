{{--
    Blok tanda tangan versi baca — dipakai modal formulir etik dan informed consent.

    $tandaTangan = data URL PNG, boleh null (formulir lama sebelum ada tanda tangan)
    $tanggal     = Carbon|null, tanggal formulir dikirim
--}}
<div>
    <div class="font-semibold mb-2">Tanda Tangan Peneliti Utama</div>
    @if ($tandaTangan)
        <img src="{{ $tandaTangan }}" alt="Tanda tangan {{ $proposal->peneliti_utama }}"
            class="border border-base-300 rounded-lg bg-white max-h-28">
    @else
        {{-- Dinyatakan terang-terangan: kotak kosong tanpa keterangan terbaca
             sebagai galat tampilan, bukan sebagai formulir yang memang belum
             bertanda tangan. --}}
        <div class="text-sm opacity-60">Belum ditandatangani.</div>
    @endif
    <div class="text-xs opacity-70 mt-1">
        {{ $proposal->peneliti_utama }}
        @if ($tanggal)
            · {{ $tanggal->translatedFormat('d F Y') }}
        @endif
    </div>
</div>

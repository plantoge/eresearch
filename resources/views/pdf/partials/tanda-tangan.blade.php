{{--
    Tanda tangan di dalam PDF.

    Hanya SVG yang digambar. Tanda tangan PNG dari formulir lama sengaja TIDAK
    dicoba dirender: dompdf memerlukan GD untuk setiap gambar raster, dan di
    server produksi GD tidak ada — mencobanya berarti seluruh pencetakan gagal
    dengan galat, bukan sekadar kehilangan satu gambar.

    Ketiadaannya dinyatakan terang-terangan, bukan dibiarkan jadi ruang kosong
    yang terbaca seolah peneliti lupa menandatangani.

    $tandaTangan — data URL, boleh null
--}}
@php use App\Support\TandaTangan; @endphp

@if (TandaTangan::adalahSvg($tandaTangan))
    <div><img src="{{ $tandaTangan }}" alt="Tanda tangan" height="56"></div>
@elseif ($tandaTangan)
    <div class="ruang"></div>
    <div style="font-size:8pt;color:#777;font-style:italic">
        Tanda tangan tersimpan dalam format lama dan tidak dapat dicetak.
        Buka formulir ini lalu tanda tangani ulang.
    </div>
@else
    <div class="ruang"></div>
@endif

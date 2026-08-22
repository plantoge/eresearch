{{--
    Informed Consent versi cetak: Lembar Informasi + Lembar Persetujuan kosong.

    Lembar Persetujuan berada di halaman tersendiri dan sengaja hanya berisi
    judul penelitian — kolom peserta dibiarkan kosong karena diisi subjek dengan
    pena. Halaman inilah yang digandakan peneliti untuk dibawa ke lapangan.

    Gaya inline dan sederhana: dompdf hanya mengerti sebagian kecil CSS, kelas
    Tailwind tidak berlaku di sini.
--}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Informed Consent — {{ $proposal->kode }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10pt; color: #111; margin: 0; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 2px; text-transform: uppercase; }
        .sub { text-align: center; font-size: 9pt; font-style: italic; margin-bottom: 14px; }
        .judul { text-align: center; font-weight: bold; margin: 10px 0 16px; }
        h2 { font-size: 10.5pt; margin: 14px 0 4px; }
        p { margin: 0 0 8px; text-align: justify; line-height: 1.5; }
        .kosong { color: #777; font-style: italic; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        td, th { border: 1px solid #444; padding: 8px 6px; vertical-align: top; }
        td.isi { height: 46px; }
        .ttd { margin-top: 26px; font-size: 10pt; }
        .ruang { height: 56px; }
        .halaman-baru { page-break-before: always; }
        .catatan { font-size: 8.5pt; margin-top: 10px; }
    </style>
</head>

<body>
    <h1>Lembar Informasi</h1>
    <div class="sub">RSPI Prof. Dr. Sulianti Saroso · {{ $proposal->kode }}</div>

    <div class="judul">“{{ $proposal->judul_penelitian }}”</div>

    @if (! $consent->merekrut_partisipan)
        {{-- Formulir tetap dicetak walau kosong: KEPK perlu melihat alasannya
             tertulis, bukan menemukan berkas yang tidak ada. --}}
        <p>
            Penelitian ini <strong>tidak merekrut partisipan secara langsung</strong>, sehingga tidak
            memerlukan lembar informasi dan persetujuan subjek.
        </p>
        <h2>Alasan</h2>
        <p>{{ $consent->alasan_tanpa_consent ?: '—' }}</p>
    @else
        <p>
            Saya adalah {{ $consent->peran_peneliti ?: '—' }} yang berasal dari
            {{ $proposal->institusi_asal ?: '—' }} yang sedang melakukan penelitian untuk
            {{ $consent->maksud_penelitian ?: '—' }}, mengundang Anda untuk berpartisipasi dalam
            penelitian ini. Keikutsertaan Anda dalam penelitian ini bersifat sukarela, jadi Anda dapat
            memutuskan untuk berpartisipasi atau sebaliknya.
        </p>

        @foreach ($bagian as $b)
            <h2>{{ $b->label() }}:</h2>
            <p>{{ $consent->bagian($b) ?: '—' }}</p>
        @endforeach

        <div class="ttd">
            Jakarta, {{ $consent->dikirim_pada?->translatedFormat('d F Y') ?: '—' }}<br>
            Peneliti,
            @include('pdf.partials.tanda-tangan', ['tandaTangan' => $consent->tanda_tangan])
            {{ $proposal->peneliti_utama }}
        </div>

        {{-- ===== Halaman terpisah: diisi subjek dengan pena ===== --}}
        <div class="halaman-baru">
            <h1>Lembar Persetujuan Untuk Ikut Serta Dalam Penelitian</h1>
            <div class="sub">Diisi dan ditandatangani oleh peserta penelitian</div>

            <p>
                Saya telah membaca atau memperoleh penjelasan, sepenuhnya menyadari, mengerti, dan
                memahami tentang tujuan, manfaat, dan risiko yang mungkin timbul dalam penelitian,
                serta telah diberi kesempatan untuk bertanya dan telah dijawab dengan memuaskan, juga
                sewaktu-waktu dapat mengundurkan diri dari keikutsertaannya, maka saya dengan sukarela
                dan tanpa tekanan/paksaan siapa pun memilih untuk <strong>setuju/tidak setuju</strong><sup>*)</sup>
                ikut serta dalam penelitian ini, yang berjudul:
            </p>

            <div class="judul">“{{ $proposal->judul_penelitian }}”</div>

            <table>
                <tr>
                    <th style="width:45%"></th>
                    <th style="width:20%">Tgl.</th>
                    <th>Tanda tangan (bila tidak bisa dapat digunakan cap jempol)</th>
                </tr>
                <tr>
                    <td class="isi">Nama Peserta:<br><br>Usia:<br><br>Alamat:</td>
                    <td class="isi"></td>
                    <td class="isi"></td>
                </tr>
                <tr>
                    <td class="isi">Nama Peneliti:</td>
                    <td class="isi"></td>
                    <td class="isi"></td>
                </tr>
                <tr>
                    <td class="isi">Nama Saksi:</td>
                    <td class="isi"></td>
                    <td class="isi"></td>
                </tr>
            </table>

            <div class="catatan">*) coret yang tidak perlu</div>
        </div>
    @endif
</body>

</html>

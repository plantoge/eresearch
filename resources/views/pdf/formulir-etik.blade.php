{{--
    Formulir Pengajuan Etik versi cetak.

    Tata letaknya sengaja meniru formulir kertas KEPK (tabel bernomor, Poin A/B/C
    berurutan) supaya berkas ini bisa langsung diarsipkan bersama formulir yang
    sudah ada. Gayanya inline dan sederhana karena dompdf hanya mengerti sebagian
    kecil CSS — kelas Tailwind tidak berlaku di sini.
--}}
@php
    $ya = fn (?bool $v) => $v === null ? '—' : ($v ? 'Ya' : 'Tidak');
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Formulir Pengajuan Etik — {{ $proposal->kode }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10pt; color: #111; margin: 0; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 2px; text-transform: uppercase; }
        .sub { text-align: center; font-size: 9pt; font-style: italic; margin-bottom: 14px; }
        h2 { font-size: 10.5pt; margin: 16px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #444; padding: 4px 6px; vertical-align: top; }
        td.no { width: 22px; text-align: center; }
        td.label { width: 40%; }
        td.centang { width: 42px; text-align: center; font-weight: bold; }
        .ket { font-size: 8pt; color: #555; }
        .ttd { margin-top: 28px; font-size: 10pt; }
        .ttd .ruang { height: 56px; }
    </style>
</head>

<body>
    <h1>Formulir Pengajuan Etik Penelitian</h1>
    <div class="sub">RSPI Prof. Dr. Sulianti Saroso — diisi oleh peneliti</div>

    <table>
        <tr>
            <td class="label">No. Protokol <span class="ket">(diisi sekretariat KEPK)</span></td>
            <td>{{ $proposal->protokolEtik?->nomor_protokol ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Nomor Proposal</td>
            <td>{{ $proposal->kode }}</td>
        </tr>
    </table>

    <h2>A. Informasi Umum</h2>
    <table>
        <tr><td class="no">1</td><td class="label">Judul Penelitian</td><td>{{ $proposal->judul_penelitian }}</td></tr>
        <tr><td class="no">2</td><td class="label">Peneliti Utama</td><td>{{ $proposal->peneliti_utama }}</td></tr>
        <tr><td class="no">3</td><td class="label">Pembimbing/Peneliti Lain</td><td>{{ $proposal->tim_peneliti ?: '—' }}</td></tr>
        <tr><td class="no">4</td><td class="label">Telp/Mobile Phone Peneliti Utama</td><td>{{ $proposal->phone ?: '—' }}</td></tr>
        <tr><td class="no">5</td><td class="label">Email</td><td>{{ $proposal->email ?: '—' }}</td></tr>
        <tr><td class="no">6</td><td class="label">Instansi dan Unit/Departemen</td><td>{{ $proposal->institusi_asal ?: '—' }}</td></tr>
        <tr><td class="no">7</td><td class="label">Sponsor atau Pemberi Grant</td><td>{{ $proposal->sponsor ?: '—' }}</td></tr>
        <tr><td class="no">8</td><td class="label">Jenis Penelitian</td><td>{{ $proposal->jenis_penelitian?->label() ?: '—' }}</td></tr>
        <tr><td class="no">9</td><td class="label">Lokasi Penelitian</td><td>{{ $proposal->lokasi_penelitian ?: '—' }}</td></tr>
    </table>

    <h2>B. Kelengkapan Dokumen Pengajuan</h2>
    <table>
        @foreach ($butir as $b)
            <tr>
                <td class="no">{{ $b->value }}</td>
                <td>
                    {{ $b->label() }}
                    @if ($b->keterangan())
                        <div class="ket">{{ $b->keterangan() }}</div>
                    @endif
                </td>
                <td class="centang">{{ $form->dicentang($b) ? 'V' : '—' }}</td>
            </tr>
        @endforeach
    </table>

    <h2>C. Informasi Lain</h2>
    <table>
        <tr>
            <td class="no">1</td>
            <td class="label">Apakah Penelitian Multisenter</td>
            <td>
                {{ $ya($form->multisenter) }}
                @if ($form->multisenter)
                    <div class="ket">
                        Senter Penelitian Utama: {{ $form->senter_utama ?: '—' }}<br>
                        Senter Penelitian Satelit: {{ $form->senter_satelit ?: '—' }}
                    </div>
                @endif
            </td>
        </tr>
        <tr>
            <td class="no">2</td>
            <td class="label">Apakah Penelitian kerja sama?</td>
            <td>
                {{ $form->kerjasama?->label() ?: '—' }}
                @if ($form->jumlah_negara)
                    <div class="ket">Jumlah negara: {{ $form->jumlah_negara }}</div>
                @endif
                <div class="ket">Melibatkan Ketua Peneliti asing: {{ $ya($form->peneliti_asing) }}</div>
            </td>
        </tr>
        <tr>
            <td class="no">3</td>
            <td class="label">Apakah Protokol ini Pernah Diajukan ke Komisi Etik Lain?</td>
            <td>
                {{ $ya($form->pernah_diajukan) }}
                @if ($form->pernah_diajukan)
                    <div class="ket">Hasil: {{ $form->disetujui_komisi_lain ? 'Disetujui' : 'Tidak Disetujui' }}</div>
                @endif
            </td>
        </tr>
        <tr>
            <td class="no">4</td>
            <td class="label">Apakah terdapat sampel biologis yang akan dikirim ke luar negeri?</td>
            <td>
                {{ $ya($form->sampel_ke_luar_negeri) }}
                @if ($form->sampel_ke_luar_negeri)
                    <div class="ket">Negara tujuan: {{ $form->negara_tujuan ?: '—' }}</div>
                @endif
            </td>
        </tr>
        <tr>
            <td class="no">5</td>
            <td class="label">Apakah produk yang diteliti akan diregistrasi ke BPOM/Kemenkes?</td>
            <td>{{ $form->registrasi_bpom ?: '—' }}</td>
        </tr>
    </table>

    <div class="ttd">
        Jakarta, {{ $form->dikirim_pada?->translatedFormat('d F Y') ?: '—' }}<br>
        Peneliti utama,
        @include('pdf.partials.tanda-tangan', ['tandaTangan' => $form->tanda_tangan])
        {{ $proposal->peneliti_utama }}
    </div>
</body>

</html>

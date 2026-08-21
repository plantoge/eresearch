<?php

namespace App\Enums;

/**
 * Poin B Formulir Pengajuan Etik: ceklist kelengkapan dokumen a–j.
 *
 * Ini DEKLARASI peneliti tentang apa yang ia lampirkan, bukan slot unggahan —
 * berkasnya tetap lewat kartu dokumen yang sudah ada. Karena itu tidak satu pun
 * butir di sini wajib dicentang: sebagian memang hanya berlaku pada penelitian
 * tertentu (lihat keterangan()), dan memaksa centang hanya akan mengajari
 * peneliti mencentang tanpa membaca.
 *
 * Huruf a–j-nya sengaja jadi nilai enum, bukan urutan array: huruf itulah yang
 * tercetak di formulir kertas dan dipakai KEPK saat menyebut butir yang kurang.
 */
enum KelengkapanDokumen: string
{
    case A = 'a';
    case B = 'b';
    case C = 'c';
    case D = 'd';
    case E = 'e';
    case F = 'f';
    case G = 'g';
    case H = 'h';
    case I = 'i';
    case J = 'j';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Summary/Ringkasan Protokol',
            self::B => 'Protokol/Proposal Lengkap Penelitian',
            self::C => 'Naskah penjelasan & Formulir Persetujuan (Informed Consent & Assent)',
            self::D => 'Instrumen pengumpulan data',
            self::E => 'Brosur Investigasi (Investigational Brochure)',
            self::F => 'Susunan tim peneliti, CV/Biodata Peneliti Utama, dan sertifikat kompetensi',
            self::G => 'Pernyataan Konflik Kepentingan Peneliti dengan Sponsor/Pemberi Dana',
            self::H => 'Persetujuan Kepala instansi/departemen/unit yang berwenang',
            self::I => 'Bukti transfer dana kaji etik',
            self::J => 'Copy Surat Pernyataan Kerahasiaan',
        };
    }

    /** Rincian butir seperti tercetak di formulir — kosong bila tidak ada. */
    public function keterangan(): string
    {
        return match ($this) {
            self::A => 'Rationale dan latar belakang; tujuan umum dan khusus; desain penelitian; cara pengumpulan data; lokasi dan waktu penelitian; analisis data',
            self::B => 'Softcopy dan hardcopy protokol penelitian bentuk PDF',
            self::C => 'Jika akan merekrut partisipan secara langsung',
            self::D => 'Misal: kuesioner, pedoman wawancara, CRF, dll',
            self::E => 'Jika ada',
            self::F => 'Misalnya sertifikat Good Clinical Practices',
            self::J => 'Jika mengambil data di RSPI Prof. Dr. Sulianti Saroso',
            default => '',
        };
    }
}

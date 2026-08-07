<?php

namespace App\Enums;

enum DocumentType: string
{
    // Tahap 1
    case SuratPengantar = 'surat_pengantar';
    case Proposal = 'proposal';
    case KajiEtik = 'kaji_etik';
    case SertifikatGcp = 'sertifikat_gcp';
    // Tahap 2
    case FormKajiEtik = 'form_kaji_etik';
    case InformedConsent = 'informed_consent';
    case Pks = 'pks';
    case KerahasiaanData = 'kerahasiaan_data';
    // Tahap 3 — pembayaran terpisah ke CRU & KEPK
    case BuktiBayarCru = 'bukti_bayar_cru';
    case BuktiBayarKepk = 'bukti_bayar_kepk';
    // Tahap 4
    case LaporanPenelitian = 'laporan_penelitian';
    case RawData = 'raw_data';
    // Output admin
    case IzinDraft = 'izin_draft';
    case IzinFinal = 'izin_final';
    case SuratPenolakan = 'surat_penolakan';
    case SuratTanggapan = 'surat_tanggapan';
    // Catatan: file tanggapan reviewer TIDAK ada di sini. Berkas itu rahasia dari
    // peneliti, jadi disimpan di tabelnya sendiri (rspi.kepk_dokumen_telaah) yang
    // tidak punya route unduh untuk peneliti — bukan disaring lewat if.

    public function label(): string
    {
        return match ($this) {
            self::SuratPengantar => 'Surat Pengantar',
            self::Proposal => 'Proposal Penelitian',
            self::KajiEtik => 'Kaji Etik (awal, opsional)',
            self::SertifikatGcp => 'Sertifikat GCP',
            self::FormKajiEtik => 'Form Kaji Etik',
            self::InformedConsent => 'Informed Consent',
            self::Pks => 'Perjanjian Kerjasama (PKS)',
            self::KerahasiaanData => 'Kerahasiaan Data',
            self::BuktiBayarCru => 'Bukti Pembayaran (CRU)',
            self::BuktiBayarKepk => 'Bukti Pembayaran (KEPK)',
            self::LaporanPenelitian => 'Laporan Penelitian',
            self::RawData => 'Raw Data Penelitian',
            self::IzinDraft => 'Surat Izin Penelitian (Draft)',
            self::IzinFinal => 'Surat Izin Penelitian (Final)',
            self::SuratPenolakan => 'Surat Penolakan',
            self::SuratTanggapan => 'Surat Tanggapan Revisi',
        };
    }

    /** @return self[] */
    public static function wajibTahap1(): array
    {
        return [self::SuratPengantar, self::Proposal];
    }

    /** @return self[] */
    public static function opsionalTahap1(): array
    {
        return [self::KajiEtik, self::SertifikatGcp];
    }

    /** @return self[] */
    public static function wajibTahap2(): array
    {
        return [self::FormKajiEtik, self::InformedConsent, self::Pks, self::KerahasiaanData];
    }

    /** Aturan validasi upload Livewire (prd §7c). */
    public function aturanValidasi(): string
    {
        return match ($this) {
            self::BuktiBayarCru, self::BuktiBayarKepk => 'file|mimes:jpg,jpeg,pdf|max:5120',
            self::RawData => 'file|mimes:xls,xlsx|max:20480',
            default => 'file|mimes:pdf|max:10240',
        };
    }

    /** Dokumen ini di-upload oleh admin (bukan peneliti). */
    public function milikAdmin(): bool
    {
        return in_array($this, [
            self::IzinDraft, self::IzinFinal, self::SuratPenolakan,
            self::SuratTanggapan,
        ], true);
    }
}

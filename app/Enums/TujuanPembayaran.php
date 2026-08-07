<?php

namespace App\Enums;

/** Tahap 3: peneliti membayar ke DUA pihak terpisah — keduanya wajib. */
enum TujuanPembayaran: string
{
    case Cru = 'cru';
    case Kepk = 'kepk';

    public function label(): string
    {
        return match ($this) {
            self::Cru => 'Pembayaran CRU',
            self::Kepk => 'Pembayaran KEPK',
        };
    }

    /**
     * Jenis dokumen bukti bayar yang bersesuaian.
     *
     * Sengaja tetap dua jenis, bukan satu `bukti_bayar` dengan pembeda di kolom
     * `tujuan`: versi dokumen naik per pasangan (proposal, jenis), jadi kalau
     * digabung bukti CRU dan KEPK saling menaikkan versi dan riwayat revisinya kacau.
     */
    public function jenisDokumen(): DocumentType
    {
        return match ($this) {
            self::Cru => DocumentType::BuktiBayarCru,
            self::Kepk => DocumentType::BuktiBayarKepk,
        };
    }
}

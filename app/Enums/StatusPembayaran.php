<?php

namespace App\Enums;

enum StatusPembayaran: string
{
    case Menunggu = 'menunggu';
    case Terverifikasi = 'terverifikasi';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu Verifikasi',
            self::Terverifikasi => 'Terverifikasi',
            self::Ditolak => 'Ditolak',
        };
    }

    /** Kelas badge daisyUI — sama pola dengan ProposalStatus::warna(). */
    public function warna(): string
    {
        return match ($this) {
            self::Menunggu => 'badge-neutral',
            self::Terverifikasi => 'badge-success',
            self::Ditolak => 'badge-error',
        };
    }
}

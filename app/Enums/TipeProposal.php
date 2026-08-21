<?php

namespace App\Enums;

/**
 * Tipe proposal — dipilih peneliti saat mengajukan, dan ikut jadi DUA DIGIT
 * PERTAMA nomor proposal (lihat ProposalWorkflow::formatKode()).
 *
 * Nilainya sengaja '01'/'02' persis seperti yang tercetak di nomor, supaya tidak
 * ada tabel pemetaan terpisah yang bisa melenceng dari kode yang terlanjur terbit.
 * Karena itu nilai ini TIDAK boleh diubah setelah ada proposal di database.
 */
enum TipeProposal: string
{
    case Internal = '01';
    case Eksternal = '02';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal',
            self::Eksternal => 'Eksternal',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Internal => 'Peneliti dari RSPI Prof. Dr. Sulianti Saroso',
            self::Eksternal => 'Peneliti dari institusi lain',
        };
    }
}

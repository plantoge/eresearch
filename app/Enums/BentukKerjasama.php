<?php

namespace App\Enums;

/**
 * Poin C.2 Formulir Pengajuan Etik: bentuk kerja sama penelitian.
 *
 * "Melibatkan Ketua Peneliti asing" TIDAK ada di sini walau di formulir kertas
 * tercetak sebagai kotak keempat di sel yang sama: ia bisa benar bersamaan
 * dengan kerja sama nasional maupun internasional, jadi memaksanya jadi salah
 * satu pilihan di sini akan membuat jawabannya saling meniadakan. Disimpan
 * sebagai kolom boolean tersendiri (`peneliti_asing`).
 */
enum BentukKerjasama: string
{
    case Bukan = 'bukan';
    case Nasional = 'nasional';
    case Internasional = 'internasional';

    public function label(): string
    {
        return match ($this) {
            self::Bukan => 'Bukan kerja sama',
            self::Nasional => 'Kerja sama nasional',
            self::Internasional => 'Internasional',
        };
    }
}

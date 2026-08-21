<?php

namespace App\Enums;

/**
 * Poin A.7 Formulir Pengajuan Etik KEPK: Experimental / Non Experimental.
 *
 * Ditanyakan sejak pengajuan awal (bukan di formulir etik) karena jawabannya
 * adalah sifat penelitiannya sendiri — sama sekali tidak berubah antar tahap,
 * dan CRU pun perlu tahu sebelum berkasnya sampai ke KEPK.
 */
enum JenisPenelitian: string
{
    case Experimental = 'experimental';
    case NonExperimental = 'non_experimental';

    public function label(): string
    {
        return match ($this) {
            self::Experimental => 'Experimental',
            self::NonExperimental => 'Non Experimental',
        };
    }
}

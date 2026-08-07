<?php

namespace App\Enums;

/**
 * Jenis telaah etik. Nilai di bawah memakai istilah standar WHO/CIOMS yang lazim
 * dipakai komite etik di Indonesia — BELUM dikonfirmasi ke KEPK RSPI Sulianti Saroso.
 *
 * Disimpan sebagai string di kolom varchar, jadi mengganti kosakatanya cukup
 * mengubah berkas ini; tidak butuh migration selama belum ada data produksi.
 */
enum JenisTelaah: string
{
    case Exempted = 'exempted';
    case Expedited = 'expedited';
    case FullBoard = 'full_board';

    public function label(): string
    {
        return match ($this) {
            self::Exempted => 'Exempted (dikecualikan)',
            self::Expedited => 'Expedited (dipercepat)',
            self::FullBoard => 'Full Board (sidang penuh)',
        };
    }
}

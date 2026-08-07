<?php

namespace App\Enums;

/**
 * Keputusan sidang etik. Sama seperti JenisTelaah, kosakatanya BELUM dikonfirmasi
 * ke KEPK RSPI dan boleh diganti tanpa migration.
 *
 * Ini bukan status alur: perpindahan status tetap lewat ProposalStatus /
 * ProposalWorkflow. Yang dicatat di sini adalah keputusan sidangnya.
 */
enum KeputusanEtik: string
{
    case Layak = 'layak';
    case TidakLayak = 'tidak_layak';

    public function label(): string
    {
        return match ($this) {
            self::Layak => 'Layak Etik',
            self::TidakLayak => 'Tidak Layak Etik',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Layak => 'badge-success',
            self::TidakLayak => 'badge-error',
        };
    }
}

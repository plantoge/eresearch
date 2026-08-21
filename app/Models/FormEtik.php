<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use App\Enums\BentukKerjasama;
use App\Enums\KelengkapanDokumen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Formulir Pengajuan Etik yang diisi peneliti — 1:1 dengan proposal.
 *
 * Menggantikan unggahan PDF `form_kaji_etik`: isinya kini terbaca sebagai data,
 * bukan lampiran yang harus diunduh dulu.
 */
class FormEtik extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.kepk_form_etik';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'proposal_id', 'kelengkapan',
        'multisenter', 'senter_utama', 'senter_satelit',
        'kerjasama', 'jumlah_negara', 'peneliti_asing',
        'pernah_diajukan', 'disetujui_komisi_lain',
        'sampel_ke_luar_negeri', 'negara_tujuan',
        'registrasi_bpom', 'dikirim_pada',
    ];

    protected $casts = [
        'kelengkapan' => 'array',
        'kerjasama' => BentukKerjasama::class,
        'multisenter' => 'boolean',
        'peneliti_asing' => 'boolean',
        'pernah_diajukan' => 'boolean',
        'disetujui_komisi_lain' => 'boolean',
        'sampel_ke_luar_negeri' => 'boolean',
        'dikirim_pada' => 'datetime',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    /**
     * Butir Poin B dicentang peneliti?
     *
     * Butir yang tidak ada di JSON berarti tidak dicentang — formulir yang
     * disimpan sebelum butir itu ada tetap terbaca sebagai "tidak dilampirkan",
     * bukan melempar error.
     */
    public function dicentang(KelengkapanDokumen $butir): bool
    {
        return (bool) ($this->kelengkapan[$butir->value] ?? false);
    }
}

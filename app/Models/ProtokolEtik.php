<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use App\Enums\JenisTelaah;
use App\Enums\KeputusanEtik;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Berkas kerja KEPK — 1:1 dengan proposal.
 *
 * `nomor_protokol` adalah penomoran KEPK sendiri dan sengaja TERPISAH dari
 * `proposal.kode` (RSPISS-YYYY-###) yang dipegang CRU.
 */
class ProtokolEtik extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.kepk_protokol_etik';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'proposal_id', 'nomor_protokol', 'jenis_telaah', 'tanggal_sidang',
        'keputusan', 'nomor_ec', 'tanggal_terbit_ec', 'berlaku_sampai',
    ];

    protected $casts = [
        'jenis_telaah' => JenisTelaah::class,
        'keputusan' => KeputusanEtik::class,
        'tanggal_sidang' => 'datetime',
        'tanggal_terbit_ec' => 'datetime',
        'berlaku_sampai' => 'date',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }
}

<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Penerbitan izin oleh CRU — draft dulu, final belakangan. 1:1 dengan proposal. */
class IzinPenelitian extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.cru_izin_penelitian';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'proposal_id', 'nomor_izin', 'tanggal_terbit_draft', 'tanggal_terbit_final',
        'berlaku_sampai', 'diterbitkan_oleh',
    ];

    protected $casts = [
        'tanggal_terbit_draft' => 'datetime',
        'tanggal_terbit_final' => 'datetime',
        'berlaku_sampai' => 'date',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function penerbit()
    {
        return $this->belongsTo(User::class, 'diterbitkan_oleh');
    }
}

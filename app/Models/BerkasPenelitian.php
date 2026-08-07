<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Berkas kerja CRU untuk Tahap 1 — 1:1 dengan proposal (partial unique di DB). */
class BerkasPenelitian extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.cru_berkas_penelitian';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'proposal_id', 'tanggal_presentasi', 'kategori_presentasi', 'media_presentasi',
        'catatan_verifikasi', 'diverifikasi_oleh', 'diverifikasi_pada',
    ];

    protected $casts = [
        'tanggal_presentasi' => 'datetime',
        'diverifikasi_pada' => 'datetime',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function verifikator()
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}

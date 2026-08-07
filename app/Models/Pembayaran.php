<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use App\Enums\StatusPembayaran;
use App\Enums\TujuanPembayaran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Satu baris per tagihan. Tahap 3 selalu menghasilkan dua: tujuan CRU dan KEPK. */
class Pembayaran extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.cru_pembayaran';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'proposal_id', 'tujuan', 'nominal', 'status', 'dokumen_id',
        'diverifikasi_oleh', 'diverifikasi_pada', 'catatan',
    ];

    protected $casts = [
        'tujuan' => TujuanPembayaran::class,
        'status' => StatusPembayaran::class,
        'nominal' => 'integer',
        'diverifikasi_pada' => 'datetime',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function dokumen()
    {
        return $this->belongsTo(ProposalDocument::class, 'dokumen_id');
    }

    public function verifikator()
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}

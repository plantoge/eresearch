<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use App\Enums\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Telaah per ronde — ditulis reviewer, dan KEPK saat menolak etik.
 * RAHASIA dari peneliti (prd §6.1): jangan pernah dipakai di query yang
 * hasilnya bisa dilihat pemilik proposal.
 */
class TelaahReviewer extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.kepk_telaah_reviewer';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'proposal_id', 'unit', 'reviewer_id', 'keputusan', 'komentar', 'ronde',
    ];

    protected $casts = [
        'unit' => Unit::class,
        'ronde' => 'integer',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function dokumen()
    {
        return $this->hasMany(DokumenTelaah::class, 'telaah_id');
    }
}

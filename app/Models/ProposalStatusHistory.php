<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use App\Enums\ProposalStatus;
use App\Enums\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Append-only by convention: baris history tidak pernah di-update
 * atau dihapus dari UI (audit trail prd §8.3).
 */
class ProposalStatusHistory extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.proposal_status_history';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'proposal_id', 'from_status', 'to_status', 'unit', 'actor_id', 'catatan',
    ];

    protected $casts = [
        'from_status' => ProposalStatus::class,
        'to_status' => ProposalStatus::class,
        'unit' => Unit::class,
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProposalDocument extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.proposal_documents';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'proposal_id', 'jenis', 'path', 'nama_asli', 'versi', 'uploaded_by',
    ];

    protected $casts = [
        'jenis' => DocumentType::class,
        'versi' => 'integer',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

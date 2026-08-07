<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Respon extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.respon';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'proposal_id', 'responden_id', 'responden', 'jenis_responden', 'saran',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function responden_user()
    {
        return $this->belongsTo(User::class, 'responden_id');
    }

    public function jawaban()
    {
        return $this->hasMany(Jawaban::class, 'respon_id');
    }
}

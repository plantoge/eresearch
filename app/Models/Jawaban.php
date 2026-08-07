<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jawaban extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.jawaban';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'respon_id', 'master_pertanyaan_id', 'master_skala_id', 'pertanyaan', 'jawaban',
    ];

    public function respon()
    {
        return $this->belongsTo(Respon::class, 'respon_id');
    }
}

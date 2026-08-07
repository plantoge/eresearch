<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterPertanyaan extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.master_pertanyaan';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['master_aspek_id', 'pertanyaan', 'is_required', 'urutan', 'status_aktif'];

    protected $casts = [
        'is_required' => 'boolean',
        'status_aktif' => 'boolean',
        'urutan' => 'integer',
    ];

    public function aspek()
    {
        return $this->belongsTo(MasterAspek::class, 'master_aspek_id');
    }
}

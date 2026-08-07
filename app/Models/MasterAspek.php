<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterAspek extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.master_aspek';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['nama_aspek', 'deskripsi', 'urutan', 'status_aktif'];

    protected $casts = [
        'status_aktif' => 'boolean',
        'urutan' => 'integer',
    ];

    public function pertanyaan()
    {
        return $this->hasMany(MasterPertanyaan::class, 'master_aspek_id')->orderBy('urutan');
    }
}

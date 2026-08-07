<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterSkala extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.master_skala';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['nama_skala', 'nilai', 'urutan'];

    protected $casts = [
        'nilai' => 'integer',
        'urutan' => 'integer',
    ];
}

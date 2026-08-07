<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InformasiKontak extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.informasi_kontak';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id'];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}

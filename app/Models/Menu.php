<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.menus';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nama', 'slug', 'route', 'icon', 'parent_id', 'urutan', 'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'urutan' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('urutan');
    }

    /** 4 nama permission turunan slug (prd §5.1). */
    public function permissionNames(): array
    {
        return array_map(fn (string $aksi) => "{$this->slug}.{$aksi}", self::AKSI);
    }

    public const AKSI = ['read', 'create', 'update', 'delete'];
}

<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use App\Enums\BagianLembarInformasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lembar Informasi Informed Consent yang diisi peneliti — 1:1 dengan proposal.
 *
 * Menggantikan unggahan PDF `informed_consent`. Lembar Persetujuan tidak
 * tersimpan di sini: ia dicetak kosong untuk ditandatangani subjek di lapangan.
 */
class InformedConsent extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.kepk_informed_consent';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'proposal_id', 'merekrut_partisipan', 'alasan_tanpa_consent',
        'peran_peneliti', 'maksud_penelitian', 'lembar_informasi',
        'tanda_tangan', 'dikirim_pada',
    ];

    protected $casts = [
        'merekrut_partisipan' => 'boolean',
        'lembar_informasi' => 'array',
        'dikirim_pada' => 'datetime',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    /**
     * Isi satu bagian Lembar Informasi.
     *
     * Bagian yang belum ada di JSON mengembalikan string kosong, bukan error:
     * formulir yang disimpan sebelum bagian itu ada tetap bisa dibuka dan
     * dicetak.
     */
    public function bagian(BagianLembarInformasi $bagian): string
    {
        return (string) ($this->lembar_informasi[$bagian->value] ?? '');
    }

    /** Sudah dikirim ke KEPK, bukan sekadar disimpan sebagai draf. */
    public function sudahDikirim(): bool
    {
        return $this->dikirim_pada !== null;
    }
}

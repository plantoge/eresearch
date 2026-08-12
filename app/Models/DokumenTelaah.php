<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * File tanggapan reviewer. Berdiri sendiri, bukan di proposal_documents —
 * peneliti tidak punya satu pun route yang menjangkau tabel ini, jadi
 * kerahasiaannya sifat struktur, bukan hasil penyaringan yang harus diingat.
 */
class DokumenTelaah extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.kepk_dokumen_telaah';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Aturan upload — ditaruh di sini, bukan ditulis ulang di komponen
     * (rules.md §3). Dulu ikut DocumentType::aturanValidasi().
     *
     * `bail|berkas_ada` di depan menahan berkas sementara Livewire yang sudah
     * kedaluwarsa; tanpa itu `mimes`/`max` melempar `UnableToRetrieveMetadata`
     * dan berujung layar 500 (lihat `berkas_ada` di AppServiceProvider).
     */
    public const ATURAN_VALIDASI = 'bail|berkas_ada|file|mimes:pdf|max:10240';

    protected $fillable = [
        'proposal_id', 'telaah_id', 'path', 'nama_asli', 'versi', 'uploaded_by',
    ];

    protected $casts = [
        'versi' => 'integer',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function telaah()
    {
        return $this->belongsTo(TelaahReviewer::class, 'telaah_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

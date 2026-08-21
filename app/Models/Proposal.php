<?php

namespace App\Models;

use App\Concerns\HasUuidAndAudit;
use App\Enums\DocumentType;
use App\Enums\JenisPenelitian;
use App\Enums\ProposalStatus;
use App\Enums\TipeProposal;
use App\Enums\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kernel pengajuan: identitas + status. Data kerja tiap unit ada di berkasnya
 * masing-masing (cru_*, kepk_*), bukan sebagai kolom di sini.
 */
class Proposal extends Model
{
    use HasUuidAndAudit, SoftDeletes;

    protected $table = 'rspi.proposal';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tipe_proposal', 'tahun', 'bulan', 'nomor', 'kode',
        'peneliti_utama', 'tim_peneliti', 'judul_penelitian',
        'institusi_asal', 'email', 'phone', 'user_id',
        'sponsor', 'jenis_penelitian', 'lokasi_penelitian',
        'status', 'unit_sekarang',
    ];

    protected $casts = [
        'status' => ProposalStatus::class,
        'tipe_proposal' => TipeProposal::class,
        'jenis_penelitian' => JenisPenelitian::class,
        'unit_sekarang' => Unit::class,
        'bulan' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Siapa boleh melihat isi proposal ini: pemiliknya, petugas CRU/KEPK, dan
     * reviewer yang benar-benar ditugaskan padanya.
     *
     * Ditaruh di model karena dipakai dua pintu masuk yang berbeda — halaman
     * proposal dan unduhan formulir etik. Sebelum ini aturannya hanya hidup di
     * Proposal\Show::mount(), jadi setiap pintu baru harus mengingat menyalinnya.
     */
    public function bolehDilihatOleh(User $user): bool
    {
        return $this->user_id === $user->id
            || $user->canAny(['antrian-cru.read', 'kaji-etik.read'])
            || ($user->can('antrian-reviewer.read')
                && $this->penugasanReviewer()->where('reviewer_id', $user->id)->exists());
    }

    public function documents()
    {
        return $this->hasMany(ProposalDocument::class, 'proposal_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(ProposalStatusHistory::class, 'proposal_id')->orderBy('created_at');
    }

    // ===== Berkas kerja CRU =====

    public function berkasPenelitian()
    {
        return $this->hasOne(BerkasPenelitian::class, 'proposal_id');
    }

    public function pembayaran()
    {
        return $this->hasMany(Pembayaran::class, 'proposal_id');
    }

    public function izinPenelitian()
    {
        return $this->hasOne(IzinPenelitian::class, 'proposal_id');
    }

    // ===== Berkas kerja KEPK =====

    public function protokolEtik()
    {
        return $this->hasOne(ProtokolEtik::class, 'proposal_id');
    }

    /** Formulir Pengajuan Etik (Poin B & C) — diisi peneliti, dibaca KEPK. */
    public function formEtik()
    {
        return $this->hasOne(FormEtik::class, 'proposal_id');
    }

    /** Lembar Informasi Informed Consent — diisi peneliti, dibaca KEPK. */
    public function informedConsent()
    {
        return $this->hasOne(InformedConsent::class, 'proposal_id');
    }

    public function telaahReviewer()
    {
        return $this->hasMany(TelaahReviewer::class, 'proposal_id')->orderBy('ronde');
    }

    public function penugasanReviewer()
    {
        return $this->hasMany(PenugasanReviewer::class, 'proposal_id');
    }

    public function dokumenTelaah()
    {
        return $this->hasMany(DokumenTelaah::class, 'proposal_id');
    }

    /** Semua reviewer yang ditugaskan sudah ACC (syarat KEPK lanjut). */
    public function semuaReviewerAcc(): bool
    {
        return $this->penugasanReviewer()->exists()
            && $this->penugasanReviewer()->where('status', '!=', PenugasanReviewer::ACC)->doesntExist();
    }

    public function respon()
    {
        return $this->hasOne(Respon::class, 'proposal_id');
    }

    /**
     * Gate survey — SATU-SATUNYA cara menjawab "sudah isi survey?".
     *
     * Dulu ada kolom boolean `isi_survey_kepuasan` yang diset saat status jadi
     * Selesai; kalau baris respon dihapus, boolean itu tetap true dan izin final
     * ikut terbuka. Keberadaan baris respon-lah faktanya, bukan penandanya.
     */
    public function sudahIsiSurvey(): bool
    {
        return $this->respon()->exists();
    }

    /** Dokumen versi terakhir per jenis. */
    public function dokumenTerakhir(DocumentType $jenis): ?ProposalDocument
    {
        return $this->documents()
            ->where('jenis', $jenis->value)
            ->orderByDesc('versi')
            ->first();
    }
}

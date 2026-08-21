<?php

namespace App\Livewire\Reviewer;

use App\Enums\DocumentType;
use App\Models\DokumenTelaah;
use App\Models\PenugasanReviewer;
use App\Models\Proposal;
use App\Models\ProposalDocument;
use App\Services\ProposalWorkflow;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

/**
 * Ruang kerja satu-layar untuk reviewer — lihat
 * docs/fitur/fitur-halaman-reviewer.md.
 *
 * Alurnya: cari → pilih proposal → baca dokumen → pilih keputusan → tulis
 * komentar → konfirmasi → simpan. Semuanya di halaman ini; tidak ada satu pun
 * langkah yang memindahkan reviewer ke URL lain, termasuk membaca PDF.
 *
 * Berdiri sendiri, TIDAK mewarisi BaseAntrian: BaseAntrian dirancang untuk pola
 * klik-baris-pindah-halaman (link ke proposal.show), sedang di sini semua
 * terjadi di komponen yang sama.
 *
 * @see .claude/specs/2026-08-12-halaman-reviewer-sederhana.md
 */
#[Layout('components.layouts.reviewer')]
class TelaahSederhana extends Component
{
    use Toast, WithFileUploads;

    public string $cari = '';

    /**
     * Tab daftar: 'perlu' = belum diperiksa, 'sudah' = sudah diperiksa
     * (Disetujui maupun Perlu Revisi). Memisahkan keduanya supaya pekerjaan
     * yang menunggu tidak tenggelam di antara yang sudah selesai.
     */
    public string $tab = self::TAB_PERLU;

    public const TAB_PERLU = 'perlu';

    public const TAB_SUDAH = 'sudah';

    public int $perPage = 15;

    /** Proposal yang sedang dibuka inline — null = tak ada yang terbuka. */
    public ?string $proposalIdTerbuka = null;

    /** Dokumen yang sedang dibaca di viewer inline — null = viewer tertutup. */
    public ?string $dokumenDibaca = null;

    /** '' | 'approve' | 'revise' — nilai internal sama dengan ProposalWorkflow. */
    public string $keputusan = '';

    public string $catatan = '';

    public $fileUpload;

    public bool $konfirmasiTampil = false;

    public function updatedCari(): void
    {
        $this->perPage = 15;
        $this->tutupProposal();
    }

    public function muatLagi(): void
    {
        $this->perPage += 15;
    }

    public function pilihTab(string $tab): void
    {
        abort_unless(in_array($tab, [self::TAB_PERLU, self::TAB_SUDAH], true), 422);

        $this->tab = $tab;
        $this->perPage = 15;
        $this->tutupProposal();
    }

    /** Klik baris: buka proposal, atau tutup kalau baris yang sama diklik lagi. */
    public function buka(string $proposalId): void
    {
        if ($this->proposalIdTerbuka === $proposalId) {
            $this->tutupProposal();

            return;
        }

        $this->proposalIdTerbuka = $proposalId;
        $this->resetForm();

        // Panel detail dirender di dalam baris daftarnya. Kalau proposal ini
        // tidak ada di tab yang sedang tampil, panelnya tak akan terlihat —
        // jadi tab ikut berpindah ke tempat proposal itu berada.
        $this->tab = $this->tabUntuk($this->penugasanTerbuka()?->status);
    }

    /** Tab tempat sebuah penugasan seharusnya muncul. */
    protected function tabUntuk(?string $statusPenugasan): string
    {
        return match ($statusPenugasan) {
            PenugasanReviewer::ACC, PenugasanReviewer::REVISI => self::TAB_SUDAH,
            PenugasanReviewer::MENUNGGU => self::TAB_PERLU,
            // Tanpa penugasan render() sudah membatalkan dengan 403; tab
            // dibiarkan apa adanya supaya tidak melompat tanpa alasan.
            default => $this->tab,
        };
    }

    protected function tutupProposal(): void
    {
        $this->proposalIdTerbuka = null;
        $this->resetForm();
    }

    /**
     * Bersihkan draf saat berpindah proposal — mencegah komentar untuk proposal
     * A tak sengaja tersimpan pada proposal B.
     */
    protected function resetForm(): void
    {
        $this->reset('catatan', 'fileUpload', 'keputusan', 'dokumenDibaca', 'konfirmasiTampil');
        $this->resetValidation();
    }

    /** Buka PDF di viewer inline (PRD §13) — bukan tab baru. */
    public function bacaDokumen(string $dokumenId): void
    {
        // Dokumen harus milik proposal yang sedang dibuka; proposal itu sendiri
        // sudah dijamin milik reviewer ini oleh guard di render().
        abort_unless(
            $this->proposalIdTerbuka
                && ProposalDocument::where('id', $dokumenId)
                    ->where('proposal_id', $this->proposalIdTerbuka)
                    ->exists(),
            403,
        );

        $this->dokumenDibaca = $dokumenId;
    }

    public function tutupDokumen(): void
    {
        $this->dokumenDibaca = null;
    }

    /** Dua tombol besar, bukan dropdown (PRD §14.1). */
    public function pilihKeputusan(string $keputusan): void
    {
        abort_unless(in_array($keputusan, ['approve', 'revise'], true), 422);

        $this->keputusan = $keputusan;
        $this->resetValidation('keputusan');
    }

    /**
     * Validasi dulu, baru tampilkan konfirmasi (PRD §17). Dipisah dari
     * simpanReview() supaya kesalahan isian ketahuan SEBELUM dialog muncul —
     * bukan setelah reviewer menekan "Ya, Simpan".
     */
    public function mintaKonfirmasi(): void
    {
        $aturan = [
            'keputusan' => 'required|in:approve,revise',
            // Komentar wajib hanya saat minta revisi (PRD §15).
            'catatan' => $this->keputusan === 'revise' ? 'required|string|min:3' : 'nullable|string',
        ];

        // Lampiran divalidasi dengan aturan yang SAMA dengan Proposal\Show.
        // Tanpa ini, berkas sementara Livewire yang sudah kedaluwarsa lolos
        // sampai ke Flysystem dan meledak jadi `UnableToRetrieveMetadata` —
        // layar error, bukan pesan. Penjaganya ada di dalam konstanta itu
        // (`bail|berkas_ada`), jadi tidak perlu diulang di sini.
        if ($this->fileUpload) {
            $aturan['fileUpload'] = DokumenTelaah::ATURAN_VALIDASI;
        }

        $this->validate(
            $aturan,
            [
                'keputusan.required' => 'Pilih dulu hasil review: Perlu Revisi atau Disetujui.',
                'catatan.required' => 'Tuliskan catatan untuk peneliti bila proposal perlu direvisi.',
                'fileUpload.file' => 'Berkas tanggapan gagal diunggah atau kedaluwarsa. Pilih berkasnya sekali lagi.',
                'fileUpload.mimes' => 'Berkas tanggapan harus PDF.',
                'fileUpload.max' => 'Berkas tanggapan maksimal 10 MB.',
            ],
            ['keputusan' => 'hasil review', 'catatan' => 'catatan', 'fileUpload' => 'berkas tanggapan'],
        );

        $this->konfirmasiTampil = true;
    }

    public function batalKonfirmasi(): void
    {
        // Hanya menutup dialog. Keputusan & komentar sengaja DIPERTAHANKAN —
        // reviewer yang batal ingin mengoreksi, bukan mengetik ulang (PRD §21).
        $this->konfirmasiTampil = false;
    }

    public function simpanReview(): void
    {
        abort_unless(auth()->user()->can('antrian-reviewer.update'), 403);

        $penugasan = $this->penugasanTerbuka();
        abort_unless($penugasan && $penugasan->status === PenugasanReviewer::MENUNGGU, 403);

        $proposal = Proposal::findOrFail($this->proposalIdTerbuka);

        try {
            app(ProposalWorkflow::class)
                ->reviewerMerespons($proposal, $this->keputusan, $this->catatan ?: null, $this->fileUpload);
        } catch (\Throwable $e) {
            // Draf reviewer tidak dibuang saat gagal (PRD §21) — dialog ditutup
            // supaya ia bisa memperbaiki, isian tetap di tempatnya.
            $this->konfirmasiTampil = false;
            $this->error('Review belum berhasil disimpan. Silakan coba kembali.');

            report($e);

            return;
        }

        $this->reset('catatan', 'fileUpload', 'keputusan', 'konfirmasiTampil');
        $this->success('Review berhasil disimpan. Proposal telah selesai diperiksa.');
        // proposalIdTerbuka sengaja dibiarkan terbuka: panel berganti sendiri
        // ke tampilan hasil (read-only) tanpa reviewer harus mencari ulang.
        // Proposalnya kini pindah ke tab "Sudah Diperiksa", jadi tabnya ikut —
        // kalau tidak, panel yang baru saja disimpan hilang dari layar.
        $this->tab = self::TAB_SUDAH;
    }

    /** Penugasan reviewer ini, disaring oleh tab yang sedang aktif (PRD §10). */
    protected function query()
    {
        $statusTab = $this->tab === self::TAB_SUDAH
            ? [PenugasanReviewer::ACC, PenugasanReviewer::REVISI]
            : [PenugasanReviewer::MENUNGGU];

        return Proposal::query()
            ->whereHas('penugasanReviewer', fn ($q) => $q
                ->where('reviewer_id', auth()->id())
                ->whereIn('status', $statusTab))
            // Dimuat sekali di sini supaya label status per baris tidak memicu
            // satu query tambahan per proposal saat dirender.
            ->with(['penugasanReviewer' => fn ($q) => $q->where('reviewer_id', auth()->id())])
            ->when($this->cari, fn ($q) => $q->where(fn ($w) => $w
                ->where('kode', 'ilike', "%{$this->cari}%")
                ->orWhere('judul_penelitian', 'ilike', "%{$this->cari}%")
                ->orWhere('peneliti_utama', 'ilike', "%{$this->cari}%")))
            ->orderByDesc('updated_at');
    }

    protected function penugasanTerbuka(): ?PenugasanReviewer
    {
        if (! $this->proposalIdTerbuka) {
            return null;
        }

        return PenugasanReviewer::where('proposal_id', $this->proposalIdTerbuka)
            ->where('reviewer_id', auth()->id())
            ->latest('created_at')
            ->first();
    }

    public function render()
    {
        $query = $this->query();
        $total = (clone $query)->count();
        $proposals = $query->take($this->perPage)->get();

        // Ringkasan (PRD §8) dihitung dari penugasan, bukan dari daftar yang
        // sedang tampil — angkanya harus benar walau pencarian sedang menyaring
        // atau baris belum semuanya ter-scroll.
        // Sekaligus jadi angka pada tab — reviewer perlu tahu isi tab sebelah
        // tanpa harus membukanya.
        $penugasanSaya = PenugasanReviewer::where('reviewer_id', auth()->id());
        $totalProposal = (clone $penugasanSaya)->count();
        $perluDiperiksa = (clone $penugasanSaya)->where('status', PenugasanReviewer::MENUNGGU)->count();

        $proposalTerbuka = $this->proposalIdTerbuka
            ? ($proposals->firstWhere('id', $this->proposalIdTerbuka) ?? Proposal::find($this->proposalIdTerbuka))
            : null;

        $penugasanTerbuka = $this->penugasanTerbuka();

        // Otorisasi baris-level: proposalIdTerbuka properti publik Livewire yang
        // bisa dimanipulasi lewat request langsung — middleware route hanya
        // menjamin peran, bukan kepemilikan baris (setara Proposal\Show::mount()).
        abort_if($proposalTerbuka && ! $penugasanTerbuka, 403);

        $dokumen = $proposalTerbuka
            ? $proposalTerbuka->documents()
                ->whereIn('jenis', array_map(
                    fn (DocumentType $d) => $d->value,
                    // FormKajiEtik disebut terpisah: ia sudah bukan berkas wajib
                    // sejak formulir etik jadi isian terstruktur, tapi proposal
                    // lama masih menyimpannya sebagai PDF — dan reviewer tetap
                    // harus bisa membacanya.
                    [...DocumentType::wajibTahap1(), ...DocumentType::wajibTahap2(), DocumentType::FormKajiEtik]
                ))
                ->orderBy('jenis')->orderByDesc('versi')->get()
                ->groupBy('jenis')->map->first()
            : collect();

        // HANYA telaah milik reviewer yang login, semua ronde — bukan semua
        // reviewer seperti Proposal\Show, supaya komentar reviewer lain pada
        // proposal yang sama tidak pernah bocor (kerahasiaan, prd.md §6.1).
        $telaahSaya = $proposalTerbuka
            ? $proposalTerbuka->telaahReviewer()->where('reviewer_id', auth()->id())->orderBy('ronde')->get()
            : collect();

        return view('livewire.reviewer.telaah-sederhana', [
            'proposals' => $proposals,
            'adaLagi' => $total > $proposals->count(),
            'totalProposal' => $totalProposal,
            'perluDiperiksa' => $perluDiperiksa,
            'sudahDiperiksa' => $totalProposal - $perluDiperiksa,
            'proposalTerbuka' => $proposalTerbuka,
            'penugasanTerbuka' => $penugasanTerbuka,
            'dokumen' => $dokumen,
            'dokumenTerbaca' => $this->dokumenDibaca ? ProposalDocument::find($this->dokumenDibaca) : null,
            'telaahSaya' => $telaahSaya,
        ])->title('Review Proposal');
    }
}

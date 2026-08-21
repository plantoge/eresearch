<?php

namespace App\Livewire\Proposal;

use App\Enums\DocumentType;
use App\Enums\JenisPenelitian;
use App\Enums\TipeProposal;
use App\Services\ProposalWorkflow;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class Create extends Component
{
    use Toast, WithFileUploads;

    /**
     * '01' internal | '02' eksternal — lihat App\Enums\TipeProposal.
     * Menentukan dua digit pertama nomor proposal, jadi harus dipilih SEBELUM
     * pengajuan dikirim; setelah nomor terbit ia tidak bisa diubah.
     */
    public string $tipe_proposal = '';

    public string $peneliti_utama = '';

    public string $tim_peneliti = '';

    public string $judul_penelitian = '';

    // Poin A Formulir Pengajuan Etik. Ditanyakan di sini, bukan di formulir etik
    // tahap 2, karena ketiganya sifat penelitian yang sudah pasti sejak awal.
    public string $sponsor = '';

    public string $jenis_penelitian = '';

    public string $lokasi_penelitian = '';

    public $surat_pengantar;      // pdf wajib

    public $proposal_penelitian;  // pdf wajib

    public $kaji_etik;            // pdf opsional

    public $sertifikat_gcp;       // pdf opsional

    public function mount()
    {
        $this->peneliti_utama = auth()->user()->name;
    }

    public function simpan(ProposalWorkflow $workflow)
    {
        $this->validate([
            'tipe_proposal' => 'required|in:01,02',
            'peneliti_utama' => 'required|string|max:255',
            'tim_peneliti' => 'nullable|string',
            'judul_penelitian' => 'required|string',
            'sponsor' => 'nullable|string|max:255',
            'jenis_penelitian' => ['required', Rule::enum(JenisPenelitian::class)],
            'lokasi_penelitian' => 'required|string|max:255',
            'surat_pengantar' => 'required|'.DocumentType::SuratPengantar->aturanValidasi(),
            'proposal_penelitian' => 'required|'.DocumentType::Proposal->aturanValidasi(),
            'kaji_etik' => 'nullable|'.DocumentType::KajiEtik->aturanValidasi(),
            'sertifikat_gcp' => 'nullable|'.DocumentType::SertifikatGcp->aturanValidasi(),
        ]);

        $user = auth()->user();

        $proposal = $workflow->ajukan([
            'peneliti_utama' => $this->peneliti_utama,
            'tim_peneliti' => $this->tim_peneliti,
            'judul_penelitian' => $this->judul_penelitian,
            'sponsor' => $this->sponsor ?: null,
            'jenis_penelitian' => $this->jenis_penelitian,
            'lokasi_penelitian' => $this->lokasi_penelitian,
            'institusi_asal' => $user->institusi_asal,
            'email' => $user->email,
            'phone' => $user->phone,
        ], TipeProposal::from($this->tipe_proposal));

        $workflow->simpanDokumen($proposal, DocumentType::SuratPengantar, $this->surat_pengantar);
        $workflow->simpanDokumen($proposal, DocumentType::Proposal, $this->proposal_penelitian);

        if ($this->kaji_etik) {
            $workflow->simpanDokumen($proposal, DocumentType::KajiEtik, $this->kaji_etik);
        }
        if ($this->sertifikat_gcp) {
            $workflow->simpanDokumen($proposal, DocumentType::SertifikatGcp, $this->sertifikat_gcp);
        }

        $this->success("Proposal {$proposal->kode} berhasil diajukan.", redirectTo: route('proposal.show', $proposal));
    }

    public function render()
    {
        return view('livewire.proposal.create')->title('Ajukan Proposal');
    }
}

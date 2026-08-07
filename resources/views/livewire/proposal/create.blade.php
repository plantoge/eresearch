@php use App\Enums\DocumentType; @endphp
<div>
    <x-mary-header title="Ajukan Proposal Baru" subtitle="Tahap 1 — berkas awal" separator />

    <x-mary-card shadow class="max-w-3xl">
        <x-mary-form wire:submit="simpan">
            <x-mary-input label="Peneliti utama" wire:model="peneliti_utama" required />
            <x-mary-textarea label="Tim peneliti" wire:model="tim_peneliti" hint="Pisahkan dengan koma" rows="2" />
            <x-mary-textarea label="Judul penelitian" wire:model="judul_penelitian" rows="3" required />

            <x-mary-file label="Surat pengantar (wajib)" :hint="DocumentType::SuratPengantar->hintUnggah()"
                wire:model="surat_pengantar" accept="application/pdf" required />
            <x-mary-file label="Proposal penelitian (wajib)" :hint="DocumentType::Proposal->hintUnggah()"
                wire:model="proposal_penelitian" accept="application/pdf" required />
            <x-mary-file label="Kaji etik (opsional)" :hint="DocumentType::KajiEtik->hintUnggah()"
                wire:model="kaji_etik" accept="application/pdf" />
            <x-mary-file label="Sertifikat GCP (opsional)" :hint="DocumentType::SertifikatGcp->hintUnggah()"
                wire:model="sertifikat_gcp" accept="application/pdf" />

            <x-slot:actions>
                <x-mary-button label="Batal" link="{{ route('proposal.index') }}" class="btn-ghost" />
                <x-mary-button label="Ajukan" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="simpan" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
</div>

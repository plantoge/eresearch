@php
    use App\Enums\DocumentType;
    use App\Enums\JenisPenelitian;
    use App\Enums\TipeProposal;

    // Radio, bukan select: pilihan ini menentukan dua digit pertama nomor
    // proposal yang terbit permanen, jadi kedua kemungkinannya harus terlihat
    // sekaligus — bukan tersembunyi di balik dropdown.
    $tipeProposal = array_map(
        fn (TipeProposal $t) => ['id' => $t->value, 'name' => $t->label().' — '.$t->keterangan()],
        TipeProposal::cases(),
    );

    // Poin A.7 formulir etik. Radio karena hanya dua kemungkinan dan keduanya
    // tercetak berdampingan di formulir aslinya.
    $jenisPenelitian = array_map(
        fn (JenisPenelitian $j) => ['id' => $j->value, 'name' => $j->label()],
        JenisPenelitian::cases(),
    );
@endphp
<div>
    <x-mary-header title="Ajukan Proposal Baru" subtitle="Tahap 1 — berkas awal" separator />

    <x-mary-card shadow class="max-w-3xl">
        <x-mary-form wire:submit="simpan">
            <x-mary-radio label="Tipe proposal" wire:model="tipe_proposal" :options="$tipeProposal"
                hint="Menentukan nomor proposal Anda dan tidak dapat diubah setelah diajukan." />

            <x-mary-input label="Peneliti utama" wire:model="peneliti_utama" required />
            <x-mary-textarea label="Tim peneliti" wire:model="tim_peneliti" hint="Pisahkan dengan koma" rows="2" />
            <x-mary-textarea label="Judul penelitian" wire:model="judul_penelitian" rows="3" required />

            {{-- Poin A formulir etik KEPK. Ditanyakan di sini supaya formulir etik
                 tahap 2 tidak menanyakan ulang hal yang sudah pasti sejak awal. --}}
            <x-mary-radio label="Jenis penelitian" wire:model="jenis_penelitian" :options="$jenisPenelitian" />
            <x-mary-input label="Lokasi penelitian" wire:model="lokasi_penelitian"
                hint="Tempat penelitian dilaksanakan" required />
            <x-mary-input label="Sponsor / pemberi grant (opsional)" wire:model="sponsor"
                hint="Kosongkan bila penelitian dibiayai sendiri" />

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

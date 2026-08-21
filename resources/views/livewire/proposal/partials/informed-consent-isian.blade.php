{{--
    Formulir Informed Consent — Lembar Informasi yang diisi peneliti.

    Lembar Persetujuan (halaman terakhir formulir asli) TIDAK ada di sini: ia
    ditandatangani subjek penelitian di lapangan, jadi aplikasi hanya mencetaknya
    kosong sebagai templat di PDF.

    Pertanyaan pembuka memakai wire:model.live karena seluruh isi lembar ini
    bergantung padanya — penelitian yang tidak merekrut partisipan tidak perlu
    melihat empat belas kotak yang tak berlaku baginya.
--}}
@php
    use App\Enums\BagianLembarInformasi;

    $yaTidak = [['id' => '1', 'name' => 'Ya'], ['id' => '0', 'name' => 'Tidak']];
    $merekrut = $informedConsent->merekrut_partisipan === '1';
@endphp

<div class="space-y-6">
    <div>
        <x-mary-radio label="Apakah penelitian ini merekrut partisipan secara langsung?" :options="$yaTidak" inline
            wire:model.live="informedConsent.merekrut_partisipan"
            hint="Pilih Tidak bila penelitian hanya memakai data/spesimen yang sudah ada tanpa menghubungi subjek." />
        @error('informedConsent.merekrut_partisipan')
            <div class="text-error text-sm">{{ $message }}</div>
        @enderror
    </div>

    @if ($informedConsent->merekrut_partisipan === '0')
        <x-mary-textarea label="Alasan tidak memerlukan informed consent"
            wire:model="informedConsent.alasan_tanpa_consent" rows="3"
            hint="Contoh: data rekam medis retrospektif, tanpa kontak dengan subjek." />
        @error('informedConsent.alasan_tanpa_consent')
            <div class="text-error text-sm">{{ $message }}</div>
        @enderror
    @endif

    @if ($merekrut)
        <x-mary-alert icon="o-information-circle" class="alert-info">
            {{ BagianLembarInformasi::pengantar() }}
        </x-mary-alert>

        {{-- Kalimat pembuka formulir: "Saya adalah ...peran... yang berasal dari
             ...instansi... yang sedang melakukan penelitian untuk ...maksud..."
             Instansi tidak ditanya — sudah ada di data pengajuan. --}}
        <div class="bg-base-200 rounded-lg p-3 space-y-3">
            <div class="text-sm">
                Saya adalah <span class="font-medium">[peran]</span> yang berasal dari
                <span class="font-medium">{{ $proposal->institusi_asal ?: '—' }}</span>
                yang sedang melakukan penelitian untuk <span class="font-medium">[maksud]</span>.
            </div>
            <x-mary-input label="Peran Anda" wire:model="informedConsent.peran_peneliti"
                hint="Misalnya: mahasiswa, dosen, dokter spesialis, analis laboratorium" />
            <x-mary-textarea label="Maksud penelitian" wire:model="informedConsent.maksud_penelitian" rows="2"
                hint="Lanjutan kalimat di atas, dalam bahasa awam" />
        </div>

        <div class="space-y-4">
            @foreach ($bagianLembarInformasi as $bagian)
                <x-mary-textarea :label="$bagian->label()" :hint="$bagian->petunjuk()" :rows="$bagian->baris()"
                    wire:model="informedConsent.lembar.{{ $bagian->value }}" />
            @endforeach
        </div>

        @include('livewire.proposal.partials.tanda-tangan-peneliti', [
            'model' => 'informedConsent.tanda_tangan',
            'nilai' => $informedConsent->tanda_tangan,
        ])
    @endif
</div>

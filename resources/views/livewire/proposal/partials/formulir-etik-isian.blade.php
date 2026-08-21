{{--
    Formulir Pengajuan Etik — bagian yang diisi peneliti (Poin A ringkas, B, C).

    Dipakai di tiga kartu aksi (kirim pertama kali, perbaikan dari KEPK, revisi
    dari reviewer) supaya pertanyaan dan urutannya tidak pernah berbeda antar
    jalur; peneliti yang mengoreksi jawabannya melihat formulir yang sama persis
    dengan yang ia isi pertama kali.

    Pertanyaan penentu memakai wire:model.live: jawaban lanjutannya baru muncul
    setelah dijawab, jadi tidak ada kolom yang tampil tanpa alasan.
--}}
@php
    use App\Enums\BentukKerjasama;
    use App\Enums\KelengkapanDokumen;

    $yaTidak = [['id' => '1', 'name' => 'Ya'], ['id' => '0', 'name' => 'Tidak']];

    $opsiKerjasama = array_map(
        fn (BentukKerjasama $k) => ['id' => $k->value, 'name' => $k->label()],
        BentukKerjasama::cases(),
    );
@endphp

<div class="space-y-6">
    {{-- A. Ditarik dari data pengajuan, bukan ditanya ulang — satu judul, satu sumber. --}}
    <div>
        <div class="font-semibold text-sm mb-2">A. Informasi Umum</div>
        <div class="grid sm:grid-cols-2 gap-2 text-sm bg-base-200 rounded-lg p-3">
            <div class="sm:col-span-2"><span class="opacity-60">Judul:</span> {{ $proposal->judul_penelitian }}</div>
            <div><span class="opacity-60">Peneliti utama:</span> {{ $proposal->peneliti_utama }}</div>
            <div><span class="opacity-60">Pembimbing/peneliti lain:</span> {{ $proposal->tim_peneliti ?: '—' }}</div>
            <div><span class="opacity-60">Telp:</span> {{ $proposal->phone ?: '—' }}</div>
            <div><span class="opacity-60">Email:</span> {{ $proposal->email ?: '—' }}</div>
            <div><span class="opacity-60">Instansi/unit:</span> {{ $proposal->institusi_asal ?: '—' }}</div>
            <div><span class="opacity-60">Sponsor/grant:</span> {{ $proposal->sponsor ?: '—' }}</div>
            <div><span class="opacity-60">Jenis penelitian:</span>
                {{ $proposal->jenis_penelitian?->label() ?: '—' }}</div>
            <div><span class="opacity-60">Lokasi penelitian:</span> {{ $proposal->lokasi_penelitian ?: '—' }}</div>
        </div>
        <div class="text-xs opacity-60 mt-1">Diambil dari data pengajuan Anda. Hubungi CRU bila ada yang keliru.</div>
    </div>

    {{-- B. Deklarasi, bukan unggahan: berkasnya tetap lewat kartu dokumen. --}}
    <div>
        <div class="font-semibold text-sm mb-1">B. Kelengkapan Dokumen Pengajuan</div>
        <div class="text-xs opacity-60 mb-2">Centang dokumen yang Anda lampirkan. Butir yang tidak berlaku untuk
            penelitian Anda boleh dibiarkan kosong.</div>
        <div class="space-y-2">
            @foreach (KelengkapanDokumen::cases() as $butir)
                <label class="flex gap-3 items-start cursor-pointer">
                    <input type="checkbox" class="checkbox checkbox-sm mt-0.5"
                        wire:model="kelengkapan.{{ $butir->value }}">
                    <span class="text-sm leading-snug">
                        <span class="font-medium">{{ $butir->value }}.</span> {{ $butir->label() }}
                        @if ($butir->keterangan())
                            <span class="block text-xs opacity-60">{{ $butir->keterangan() }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- C. Setiap pertanyaan berlaku untuk semua penelitian, jadi semuanya wajib dijawab. --}}
    <div class="space-y-4">
        <div class="font-semibold text-sm">C. Informasi Lain</div>

        <x-mary-radio label="1. Apakah penelitian multisenter?" :options="$yaTidak" inline
            wire:model.live="formEtik.multisenter" />
        @error('formEtik.multisenter')
            <div class="text-error text-sm">{{ $message }}</div>
        @enderror
        @if (($formEtik['multisenter'] ?? '') === '1')
            <div class="pl-4 space-y-3 border-l-2 border-base-300">
                <x-mary-input label="Senter penelitian utama" wire:model="formEtik.senter_utama" />
                <x-mary-input label="Senter penelitian satelit (opsional)" wire:model="formEtik.senter_satelit" />
            </div>
        @endif

        <x-mary-radio label="2. Apakah penelitian kerja sama?" :options="$opsiKerjasama"
            wire:model.live="formEtik.kerjasama" />
        @error('formEtik.kerjasama')
            <div class="text-error text-sm">{{ $message }}</div>
        @enderror
        @if (($formEtik['kerjasama'] ?? '') === BentukKerjasama::Internasional->value)
            <div class="pl-4 border-l-2 border-base-300">
                <x-mary-input label="Jumlah negara" wire:model="formEtik.jumlah_negara"
                    hint="Sebutkan jumlah negara yang terlibat" />
            </div>
        @endif
        <x-mary-radio label="Melibatkan Ketua Peneliti asing?" :options="$yaTidak" inline
            wire:model="formEtik.peneliti_asing" hint="Bila ya, lampirkan izinnya bersama berkas pengajuan." />
        @error('formEtik.peneliti_asing')
            <div class="text-error text-sm">{{ $message }}</div>
        @enderror

        <x-mary-radio label="3. Apakah protokol ini pernah diajukan ke komisi etik lain?" :options="$yaTidak" inline
            wire:model.live="formEtik.pernah_diajukan" />
        @error('formEtik.pernah_diajukan')
            <div class="text-error text-sm">{{ $message }}</div>
        @enderror
        @if (($formEtik['pernah_diajukan'] ?? '') === '1')
            <div class="pl-4 border-l-2 border-base-300">
                <x-mary-radio label="Hasilnya" :options="[['id' => '1', 'name' => 'Disetujui'], ['id' => '0', 'name' => 'Tidak Disetujui']]" inline
                    wire:model="formEtik.disetujui_komisi_lain" hint="Lampirkan copy dokumen persetujuannya." />
                @error('formEtik.disetujui_komisi_lain')
                    <div class="text-error text-sm">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <x-mary-radio label="4. Apakah ada sampel biologis yang dikirim ke luar negeri?" :options="$yaTidak" inline
            wire:model.live="formEtik.sampel_ke_luar_negeri" />
        @error('formEtik.sampel_ke_luar_negeri')
            <div class="text-error text-sm">{{ $message }}</div>
        @enderror
        @if (($formEtik['sampel_ke_luar_negeri'] ?? '') === '1')
            <div class="pl-4 border-l-2 border-base-300">
                <x-mary-input label="Negara tujuan" wire:model="formEtik.negara_tujuan"
                    hint="Lampirkan draft Material Transfer Agreement (MTA)." />
            </div>
        @endif

        <x-mary-textarea label="5. Apakah produk yang diteliti akan diregistrasi ke BPOM/Kemenkes?"
            wire:model="formEtik.registrasi_bpom" rows="2" hint="Tulis '—' bila tidak berlaku." />
    </div>
</div>

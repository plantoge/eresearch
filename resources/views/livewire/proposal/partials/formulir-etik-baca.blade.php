{{--
    Formulir Pengajuan Etik — tampilan baca (isi modal di kartu Dokumen).

    Sengaja hanya membaca $formulir dan $proposal, tanpa properti komponen, supaya
    bisa dipakai ulang tanpa menuntut Show punya state tertentu.
--}}
@php
    use App\Enums\KelengkapanDokumen;

    $ya = fn (?bool $v) => $v === null ? '—' : ($v ? 'Ya' : 'Tidak');
@endphp

<div class="space-y-5 text-sm">
    <div>
        <div class="font-semibold mb-2">A. Informasi Umum</div>
        <div class="grid sm:grid-cols-2 gap-2">
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
    </div>

    <div>
        <div class="font-semibold mb-2">B. Kelengkapan Dokumen Pengajuan</div>
        <div class="space-y-1">
            @foreach (KelengkapanDokumen::cases() as $butir)
                @php $ada = $formulir->dicentang($butir); @endphp
                <div class="flex gap-2 items-start {{ $ada ? '' : 'opacity-50' }}">
                    <span class="mt-0.5">
                        @if ($ada)
                            <x-mary-icon name="o-check-circle" class="w-4 h-4 text-success" />
                        @else
                            <x-mary-icon name="o-minus-circle" class="w-4 h-4" />
                        @endif
                    </span>
                    <span class="leading-snug"><span class="font-medium">{{ $butir->value }}.</span>
                        {{ $butir->label() }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div>
        <div class="font-semibold mb-2">C. Informasi Lain</div>
        <div class="space-y-2">
            <div>
                <span class="opacity-60">1. Multisenter:</span> {{ $ya($formulir->multisenter) }}
                @if ($formulir->multisenter)
                    <div class="text-xs opacity-70 pl-4">
                        Senter utama: {{ $formulir->senter_utama ?: '—' }} ·
                        Senter satelit: {{ $formulir->senter_satelit ?: '—' }}
                    </div>
                @endif
            </div>
            <div>
                <span class="opacity-60">2. Kerja sama:</span> {{ $formulir->kerjasama?->label() ?: '—' }}
                <div class="text-xs opacity-70 pl-4">
                    @if ($formulir->jumlah_negara)
                        Jumlah negara: {{ $formulir->jumlah_negara }} ·
                    @endif
                    Ketua peneliti asing: {{ $ya($formulir->peneliti_asing) }}
                </div>
            </div>
            <div>
                <span class="opacity-60">3. Pernah diajukan ke komisi etik lain:</span>
                {{ $ya($formulir->pernah_diajukan) }}
                @if ($formulir->pernah_diajukan)
                    <div class="text-xs opacity-70 pl-4">
                        Hasil: {{ $formulir->disetujui_komisi_lain ? 'Disetujui' : 'Tidak Disetujui' }}
                    </div>
                @endif
            </div>
            <div>
                <span class="opacity-60">4. Sampel biologis ke luar negeri:</span>
                {{ $ya($formulir->sampel_ke_luar_negeri) }}
                @if ($formulir->sampel_ke_luar_negeri)
                    <div class="text-xs opacity-70 pl-4">Negara tujuan: {{ $formulir->negara_tujuan ?: '—' }}</div>
                @endif
            </div>
            <div>
                <span class="opacity-60">5. Registrasi BPOM/Kemenkes:</span>
                {{ $formulir->registrasi_bpom ?: '—' }}
            </div>
        </div>
    </div>
</div>

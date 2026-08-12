@php
    /**
     * Bahasa status reviewer (PRD §4.4 & §19) — sengaja TIDAK memakai
     * ProposalStatus mentah ("Menunggu Review Reviewer") yang ditulis untuk
     * staf, bukan untuk dokter. Yang relevan bagi reviewer adalah status
     * penugasannya sendiri.
     */
    $labelStatus = fn (?string $status) => match ($status) {
        \App\Models\PenugasanReviewer::ACC => 'Disetujui',
        \App\Models\PenugasanReviewer::REVISI => 'Perlu Revisi',
        default => 'Belum Diperiksa',
    };

    $warnaStatus = fn (?string $status) => match ($status) {
        \App\Models\PenugasanReviewer::ACC => 'bg-success/15 text-success-content border-success/40',
        \App\Models\PenugasanReviewer::REVISI => 'bg-warning/15 text-warning-content border-warning/40',
        default => 'bg-base-300/60 border-base-300',
    };
@endphp

<div>
    {{-- ===== Judul halaman ===== --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Review Proposal</h1>
        <p class="opacity-70 mt-1">Selamat datang, {{ auth()->user()->name }}</p>
    </div>

    {{-- ===== Ringkasan (PRD §8) ===== --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-base-100 border border-base-300 rounded-xl p-6 text-center">
            <div class="text-4xl font-bold">{{ $totalProposal }}</div>
            <div class="mt-1 text-base opacity-70">TOTAL PROPOSAL</div>
        </div>
        {{-- Angka terpenting bagi reviewer (PRD §8.2) — diberi warna paling kuat. --}}
        <div class="bg-warning/15 border-2 border-warning/50 rounded-xl p-6 text-center">
            <div class="text-4xl font-bold">{{ $perluDiperiksa }}</div>
            <div class="mt-1 text-base font-medium">PERLU DIPERIKSA</div>
        </div>
        <div class="bg-base-100 border border-base-300 rounded-xl p-6 text-center">
            <div class="text-4xl font-bold">{{ $sudahDiperiksa }}</div>
            <div class="mt-1 text-base opacity-70">SUDAH DIPERIKSA</div>
        </div>
    </div>

    {{-- ===== Pencarian (PRD §9) — otomatis saat mengetik, tanpa tombol Cari ===== --}}
    <div class="relative mb-8">
        <x-mary-icon name="o-magnifying-glass"
            class="w-6 h-6 absolute left-4 top-1/2 -translate-y-1/2 opacity-50 pointer-events-none" />
        <input type="search" wire:model.live.debounce.300ms="cari"
            placeholder="Cari nama peneliti atau judul penelitian..."
            class="input input-bordered w-full h-[56px] pl-13 text-lg bg-base-100">
    </div>

    {{-- ===== Daftar proposal (PRD §10) ===== --}}
    <h2 class="text-base font-bold tracking-wide opacity-70 mb-3">DAFTAR PROPOSAL</h2>

    <div class="space-y-3">
        @forelse ($proposals as $p)
            @php $statusSaya = $p->penugasanReviewer->firstWhere('reviewer_id', auth()->id())?->status; @endphp

            <div wire:key="proposal-{{ $p->id }}">
                {{-- Seluruh kartu adalah area klik (PRD §4.3 — area klik besar). --}}
                <button type="button" wire:click="buka('{{ $p->id }}')"
                    class="w-full text-left bg-base-100 border-2 rounded-xl p-5 transition
                        hover:border-primary/60 hover:bg-primary/5
                        {{ $proposalTerbuka?->id === $p->id ? 'border-primary' : 'border-base-300' }}">
                    <div class="font-semibold text-xl">{{ $p->peneliti_utama }}</div>
                    <div class="mt-1 opacity-80">{{ $p->judul_penelitian }}</div>
                    <div class="mt-3 flex items-center gap-3 flex-wrap">
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-base font-medium {{ $warnaStatus($statusSaya) }}">
                            {{ $labelStatus($statusSaya) }}
                        </span>
                        <span class="text-base opacity-60 font-mono">{{ $p->kode }}</span>
                    </div>
                </button>

                {{-- ===== Panel detail, muncul di tempat (PRD §11) ===== --}}
                @if ($proposalTerbuka?->id === $p->id)
                    <div class="mt-3 bg-base-100 border-2 border-primary/40 rounded-xl p-6 space-y-8">

                        {{-- Detail proposal --}}
                        <section>
                            <h3 class="text-base font-bold tracking-wide opacity-70 mb-4">DETAIL PROPOSAL</h3>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                                <div>
                                    <dt class="text-base opacity-60">Peneliti</dt>
                                    <dd class="font-medium">{{ $proposalTerbuka->peneliti_utama }}</dd>
                                </div>
                                <div>
                                    <dt class="text-base opacity-60">Nomor Proposal</dt>
                                    <dd class="font-medium font-mono">{{ $proposalTerbuka->kode }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-base opacity-60">Judul Penelitian</dt>
                                    <dd class="font-medium">{{ $proposalTerbuka->judul_penelitian }}</dd>
                                </div>
                                <div>
                                    <dt class="text-base opacity-60">Institusi Asal</dt>
                                    <dd class="font-medium">{{ $proposalTerbuka->institusi_asal ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-base opacity-60">Tanggal Pengajuan</dt>
                                    <dd class="font-medium">{{ $proposalTerbuka->created_at?->translatedFormat('j F Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-base opacity-60">Status</dt>
                                    <dd class="font-medium">{{ $labelStatus($penugasanTerbuka?->status) }}</dd>
                                </div>
                            </dl>
                        </section>

                        {{-- Dokumen (PRD §12) --}}
                        <section>
                            <h3 class="text-base font-bold tracking-wide opacity-70 mb-4">DOKUMEN PENELITIAN</h3>

                            @forelse ($dokumen as $jenis => $doc)
                                @php $tipe = \App\Enums\DocumentType::from($jenis); @endphp
                                <div wire:key="dok-{{ $doc->id }}"
                                    class="flex items-center justify-between gap-4 flex-wrap py-4 border-b border-base-200 last:border-0">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <x-mary-icon name="o-document-text" class="w-7 h-7 shrink-0 opacity-60 mt-0.5" />
                                        <div class="min-w-0">
                                            <div class="font-medium">{{ $tipe->label() }}</div>
                                            <div class="text-base opacity-60 truncate">{{ $doc->nama_asli }}</div>
                                        </div>
                                    </div>
                                    <button type="button" wire:click="bacaDokumen('{{ $doc->id }}')"
                                        class="btn btn-primary min-h-[48px] h-[48px] px-6 text-base normal-case
                                            {{ $dokumenDibaca === $doc->id ? '' : 'btn-outline' }}">
                                        BACA DOKUMEN
                                    </button>
                                </div>
                            @empty
                                <p class="opacity-60">Belum ada dokumen yang dapat dibaca.</p>
                            @endforelse
                        </section>

                        {{-- Pembaca PDF di halaman yang sama (PRD §13) --}}
                        @if ($dokumenTerbaca)
                            <section wire:key="viewer-{{ $dokumenTerbaca->id }}">
                                <div class="flex items-center justify-between gap-4 mb-3 flex-wrap">
                                    <h3 class="text-base font-bold tracking-wide opacity-70">
                                        SEDANG DIBACA — {{ $dokumenTerbaca->jenis->label() }}
                                    </h3>
                                    <button type="button" wire:click="tutupDokumen"
                                        class="btn btn-ghost min-h-[48px] h-[48px] px-5 text-base normal-case">
                                        <x-mary-icon name="o-x-mark" class="w-5 h-5" />
                                        Tutup Dokumen
                                    </button>
                                </div>

                                {{-- Pembaca bawaan browser: sudah punya nomor halaman, maju/mundur,
                                     zoom, dan layar penuh, tanpa menambah pustaka JS dari luar
                                     (larangan CDN di docs/rules.md). --}}
                                <iframe
                                    src="{{ route('dokumen.download', ['document' => $dokumenTerbaca, 'baca' => 1]) }}"
                                    title="{{ $dokumenTerbaca->nama_asli }}"
                                    class="w-full h-[75vh] min-h-[520px] rounded-xl border-2 border-base-300 bg-base-200"></iframe>

                                <p class="mt-2 text-base opacity-60">
                                    Dokumen tidak tampil?
                                    <a href="{{ route('dokumen.download', $dokumenTerbaca) }}"
                                        class="link link-primary">Unduh dokumen ini</a>
                                    untuk membukanya di komputer Anda.
                                </p>
                            </section>
                        @endif

                        {{-- ===== Hasil review ===== --}}
                        @if ($penugasanTerbuka?->status === \App\Models\PenugasanReviewer::MENUNGGU)
                            <section>
                                <h3 class="text-base font-bold tracking-wide opacity-70 mb-4">HASIL REVIEW</h3>

                                {{-- Dua tombol besar, bukan dropdown (PRD §14.1) --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <button type="button" wire:click="pilihKeputusan('revise')"
                                        class="flex items-center justify-center gap-3 min-h-[64px] rounded-xl border-2 text-lg font-semibold transition
                                            {{ $keputusan === 'revise'
                                                ? 'bg-warning text-warning-content border-warning'
                                                : 'bg-base-100 border-base-300 hover:border-warning' }}">
                                        <x-mary-icon name="o-pencil-square" class="w-6 h-6" />
                                        PERLU REVISI
                                    </button>
                                    <button type="button" wire:click="pilihKeputusan('approve')"
                                        class="flex items-center justify-center gap-3 min-h-[64px] rounded-xl border-2 text-lg font-semibold transition
                                            {{ $keputusan === 'approve'
                                                ? 'bg-success text-success-content border-success'
                                                : 'bg-base-100 border-base-300 hover:border-success' }}">
                                        <x-mary-icon name="o-check-circle" class="w-6 h-6" />
                                        DISETUJUI
                                    </button>
                                </div>
                                @error('keputusan')
                                    <p class="mt-3 text-error font-medium">{{ $message }}</p>
                                @enderror

                                {{-- Komentar (PRD §15) --}}
                                <div class="mt-6">
                                    <label for="catatan" class="block font-medium mb-2">
                                        Catatan / Komentar Reviewer
                                        @if ($keputusan === 'revise')
                                            <span class="text-error">(wajib diisi)</span>
                                        @else
                                            <span class="opacity-60">(boleh dikosongkan)</span>
                                        @endif
                                    </label>
                                    <textarea id="catatan" wire:model="catatan" rows="5"
                                        placeholder="Tuliskan catatan atau masukan untuk peneliti..."
                                        class="textarea textarea-bordered w-full text-lg leading-relaxed bg-base-100"></textarea>
                                    @error('catatan')
                                        <p class="mt-2 text-error font-medium">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Lampiran opsional — disembunyikan supaya tidak menambah
                                     beban visual bagi yang tidak memerlukannya. --}}
                                <details class="mt-4">
                                    <summary class="cursor-pointer opacity-70 py-2">
                                        Lampirkan berkas tanggapan (opsional)
                                    </summary>
                                    <input type="file" wire:model="fileUpload" accept="application/pdf"
                                        class="file-input file-input-bordered w-full mt-2 h-[52px] text-base">
                                    @error('fileUpload')
                                        <p class="mt-2 text-error font-medium">{{ $message }}</p>
                                    @enderror
                                </details>

                                {{-- Tombol simpan besar (PRD §16) --}}
                                <button type="button" wire:click="mintaKonfirmasi" wire:loading.attr="disabled"
                                    class="btn btn-primary w-full min-h-[56px] h-[56px] mt-6 text-lg font-bold normal-case">
                                    SIMPAN HASIL REVIEW
                                </button>
                            </section>
                        @else
                            {{-- Sudah diperiksa — hasil ditampilkan, form tidak lagi muncul. --}}
                            <section>
                                <h3 class="text-base font-bold tracking-wide opacity-70 mb-4">HASIL REVIEW ANDA</h3>
                                <div class="flex items-center gap-3 p-4 rounded-xl border-2 {{ $warnaStatus($penugasanTerbuka?->status) }}">
                                    <x-mary-icon name="o-check-circle" class="w-7 h-7 shrink-0" />
                                    <span class="font-semibold">
                                        Sudah Diperiksa — {{ $labelStatus($penugasanTerbuka?->status) }}
                                    </span>
                                </div>

                                @foreach ($telaahSaya as $t)
                                    <div wire:key="telaah-{{ $t->id }}" class="mt-4 pl-4 border-l-4 border-base-300">
                                        <div class="text-base opacity-60">
                                            Pemeriksaan ke-{{ $t->ronde }} ·
                                            {{ $t->created_at?->translatedFormat('j F Y, H:i') }} ·
                                            {{ $t->keputusan === 'approve' ? 'Disetujui' : 'Perlu Revisi' }}
                                        </div>
                                        <p class="mt-1 whitespace-pre-line">{{ $t->komentar ?: '—' }}</p>
                                    </div>
                                @endforeach
                            </section>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            {{-- Empty state (PRD §20) --}}
            <div class="bg-base-100 border border-base-300 rounded-xl py-16 px-6 text-center">
                @if ($cari)
                    <p class="text-xl font-semibold">Proposal tidak ditemukan</p>
                    <p class="mt-2 opacity-70">Coba gunakan nama peneliti atau judul penelitian yang berbeda.</p>
                @else
                    <p class="text-xl font-semibold">Belum ada proposal</p>
                    <p class="mt-2 opacity-70">Saat ini tidak ada proposal yang perlu Anda periksa.</p>
                @endif
            </div>
        @endforelse

        @if ($adaLagi)
            <div x-intersect="$wire.muatLagi()" class="py-6 text-center" wire:key="sentinel-{{ $proposals->count() }}">
                <span class="loading loading-dots loading-lg opacity-50"></span>
            </div>
        @endif
    </div>

    {{-- ===== Konfirmasi sebelum simpan (PRD §17) ===== --}}
    @if ($konfirmasiTampil)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
            <div class="bg-base-100 rounded-2xl shadow-xl w-full max-w-lg p-8">
                <h2 class="text-2xl font-bold">Apakah Anda yakin dengan hasil review ini?</h2>

                <div class="mt-6">
                    <div class="text-base opacity-60">Hasil</div>
                    <div class="mt-1 text-xl font-bold">
                        {{ $keputusan === 'approve' ? 'DISETUJUI' : 'PERLU REVISI' }}
                    </div>
                </div>

                @if ($catatan)
                    <div class="mt-4">
                        <div class="text-base opacity-60">Catatan Anda</div>
                        <p class="mt-1 whitespace-pre-line">{{ $catatan }}</p>
                    </div>
                @endif

                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                    <button type="button" wire:click="batalKonfirmasi"
                        class="btn btn-outline flex-1 min-h-[52px] h-[52px] text-base normal-case">
                        KEMBALI
                    </button>
                    <button type="button" wire:click="simpanReview" wire:loading.attr="disabled"
                        class="btn btn-primary flex-1 min-h-[52px] h-[52px] text-base font-bold normal-case">
                        YA, SIMPAN
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

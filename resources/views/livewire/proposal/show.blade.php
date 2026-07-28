@php use App\Enums\ProposalStatus; use App\Enums\DocumentType; @endphp
<div>
    <x-mary-header :title="$proposal->kode" separator>
        <x-slot:subtitle>
            <span class="badge {{ $proposal->status->warna() }}">{{ $proposal->status->value }}</span>
            @if ($proposal->status->tahapan())
                <span class="badge badge-outline ml-1">Tahap {{ $proposal->status->tahapan() }}</span>
            @endif
            @if ($proposal->unit_sekarang)
                <span class="badge badge-ghost ml-1">{{ $proposal->unit_sekarang->label() }}</span>
            @endif
        </x-slot:subtitle>
    </x-mary-header>

    <div class="grid lg:grid-cols-3 gap-6">
        {{-- Kolom kiri: detail + dokumen + aksi --}}
        <div class="lg:col-span-2 space-y-6">
            <x-mary-card title="Detail Proposal" shadow>
                <div class="grid sm:grid-cols-2 gap-3 text-sm">
                    <div><span class="opacity-60">Peneliti utama:</span><br>{{ $proposal->peneliti_utama }}</div>
                    <div><span class="opacity-60">Institusi:</span><br>{{ $proposal->institusi_asal ?: '—' }}</div>
                    <div class="sm:col-span-2"><span class="opacity-60">Judul:</span><br>{{ $proposal->judul_penelitian }}</div>
                    <div class="sm:col-span-2"><span class="opacity-60">Tim:</span><br>{{ $proposal->tim_peneliti ?: '—' }}</div>
                    @if ($proposal->tanggal_presentasi)
                        <div><span class="opacity-60">Presentasi:</span><br>{{ $proposal->tanggal_presentasi->format('d/m/Y H:i') }}</div>
                        <div><span class="opacity-60">Kategori / media:</span><br>{{ $proposal->kategori_presentasi }} · {{ $proposal->media_presentasi }}</div>
                    @endif
                </div>
            </x-mary-card>

            <x-mary-card title="Dokumen" shadow>
                @forelse ($dokumen as $jenis => $files)
                    @php $latest = $files->first(); $tipe = DocumentType::from($jenis); @endphp
                    <div class="flex items-center justify-between py-2 border-b border-base-200 last:border-0">
                        <div>
                            <div class="font-medium text-sm">{{ $tipe->label() }}</div>
                            <div class="text-xs opacity-60">v{{ $latest->versi }} · {{ $latest->nama_asli }} · {{ $latest->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <x-mary-button icon="o-arrow-down-tray" link="{{ route('dokumen.download', $latest) }}" class="btn-ghost btn-sm" external
                            tooltip="{{ $tipe === DocumentType::IzinFinal && ! $proposal->isi_survey_kepuasan ? 'Terkunci — isi survey dulu' : 'Unduh' }}" />
                    </div>
                @empty
                    <div class="opacity-60 text-sm">Belum ada dokumen.</div>
                @endforelse
            </x-mary-card>

            {{-- ===== PANEL AKSI PENELITI ===== --}}
            @if ($isPemilik)
                @if ($proposal->status === ProposalStatus::PerluRevisiProposal)
                    <x-mary-card title="Perbaiki Proposal" subtitle="Unggah revisi proposal Anda" shadow>
                        <x-mary-form wire:submit="kirimRevisi">
                            <x-mary-file label="Proposal revisi (PDF)" wire:model="fileUpload" accept="application/pdf" required />
                            <x-mary-textarea label="Catatan (opsional)" wire:model="catatan" rows="2" />
                            <x-slot:actions><x-mary-button label="Kirim Revisi" type="submit" class="btn-primary" spinner="kirimRevisi" /></x-slot:actions>
                        </x-mary-form>
                    </x-mary-card>
                @elseif ($proposal->status === ProposalStatus::MenungguKelengkapanBerkasEtik)
                    <x-mary-card title="Lengkapi Berkas Kaji Etik" subtitle="Tahap 2 — semua wajib PDF" shadow>
                        <x-mary-form wire:submit="kirimBerkasEtik">
                            @foreach (DocumentType::wajibTahap2() as $jenis)
                                <x-mary-file :label="$jenis->label()" wire:model="fileEtik.{{ $jenis->value }}" accept="application/pdf" required />
                            @endforeach
                            <x-slot:actions><x-mary-button label="Kirim ke KEPK" type="submit" class="btn-primary" spinner="kirimBerkasEtik" /></x-slot:actions>
                        </x-mary-form>
                    </x-mary-card>
                @elseif ($proposal->status === ProposalStatus::PerluRevisiReviewer)
                    <x-mary-card title="Perbaiki Berkas Etik" subtitle="Sesuai komentar Reviewer — unggah berkas yang direvisi saja" shadow>
                        <x-mary-form wire:submit="kirimRevisiEtik">
                            @foreach (DocumentType::wajibTahap2() as $jenis)
                                <x-mary-file :label="$jenis->label()" wire:model="fileEtik.{{ $jenis->value }}" accept="application/pdf" />
                            @endforeach
                            @error('fileEtik')<div class="text-error text-sm">{{ $message }}</div>@enderror
                            <x-mary-textarea label="Catatan (opsional)" wire:model="catatan" rows="2" />
                            <x-slot:actions><x-mary-button label="Kirim Ulang" type="submit" class="btn-primary" spinner="kirimRevisiEtik" /></x-slot:actions>
                        </x-mary-form>
                    </x-mary-card>
                @elseif ($proposal->status === ProposalStatus::MenungguPembayaran)
                    <x-mary-card title="Pembayaran" subtitle="Tahap 3 — dua pembayaran terpisah: CRU & KEPK" shadow>
                        @if ($kontak)
                            <x-mary-alert icon="o-banknotes" class="alert-info mb-3">
                                {{ $kontak->nama_bank }} · {{ $kontak->nomor_rekening }} a.n. {{ $kontak->pemilik_rekening }}
                                @if ($kontak->deskripsi_biaya)<br><span class="text-xs">{{ $kontak->deskripsi_biaya }}</span>@endif
                            </x-mary-alert>
                        @endif
                        <x-mary-form wire:submit="kirimBuktiBayar">
                            <x-mary-file label="Bukti pembayaran CRU (JPG/PDF)" wire:model="fileBayarCru" required />
                            <x-mary-file label="Bukti pembayaran KEPK (JPG/PDF)" wire:model="fileBayarKepk" required />
                            <x-slot:actions><x-mary-button label="Kirim Bukti" type="submit" class="btn-primary" spinner="kirimBuktiBayar" /></x-slot:actions>
                        </x-mary-form>
                    </x-mary-card>
                @elseif ($proposal->status === ProposalStatus::PelaksanaanPenelitian)
                    <x-mary-card title="Laporkan Hasil Penelitian" subtitle="Tahap 4 — laporan (PDF) + raw data (Excel)" shadow>
                        <x-mary-form wire:submit="kirimLaporan">
                            <x-mary-file label="Laporan penelitian (PDF)" wire:model="fileLaporan" accept="application/pdf" required />
                            <x-mary-file label="Raw data (XLS/XLSX)" wire:model="fileRawData" required />
                            <x-slot:actions><x-mary-button label="Kirim Laporan" type="submit" class="btn-primary" spinner="kirimLaporan" /></x-slot:actions>
                        </x-mary-form>
                    </x-mary-card>
                @elseif ($proposal->status === ProposalStatus::MenungguSurveyKepuasan)
                    <x-mary-card title="Survey Kepuasan" subtitle="Wajib diisi sebelum surat izin final dapat diunduh" shadow>
                        <x-mary-form wire:submit="kirimSurvey">
                            @foreach ($aspekSurvey as $aspek)
                                <div class="font-semibold mt-2">{{ $aspek->nama_aspek }}</div>
                                @foreach ($aspek->pertanyaan as $p)
                                    <div class="text-sm mb-1">{{ $p->pertanyaan }} @if($p->is_required)<span class="text-error">*</span>@endif</div>
                                    <div class="flex flex-wrap gap-3 mb-2">
                                        @foreach ($skalaSurvey as $skala)
                                            <label class="flex items-center gap-1 text-xs cursor-pointer">
                                                <input type="radio" class="radio radio-xs radio-primary"
                                                    wire:model="jawabanSurvey.{{ $p->id }}" value="{{ $skala->id }}">
                                                {{ $skala->nama_skala }}
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach
                            @endforeach
                            @error('jawabanSurvey')<div class="text-error text-sm">{{ $message }}</div>@enderror
                            <x-mary-textarea label="Saran" wire:model="saran" rows="3" />
                            <x-slot:actions><x-mary-button label="Kirim Survey & Buka Unduhan Izin" type="submit" class="btn-primary" spinner="kirimSurvey" /></x-slot:actions>
                        </x-mary-form>
                    </x-mary-card>
                @endif
            @endif

            {{-- ===== PANEL AKSI CRU ===== --}}
            @if ($isCru && in_array($proposal->status, [ProposalStatus::MenungguVerifikasiBerkas, ProposalStatus::MenungguVerifikasiRevisi, ProposalStatus::MenungguPresentasi], true))
                <x-mary-card title="Aksi CRU" subtitle="Review berkas / hasil presentasi" shadow>
                    <x-mary-textarea label="Catatan" wire:model="catatan" rows="2" />
                    <x-mary-file label="Lampiran (surat tanggapan / penolakan, PDF)" wire:model="fileUpload" accept="application/pdf" />
                    <div class="grid sm:grid-cols-3 gap-2 mt-3">
                        <x-mary-input label="Tanggal presentasi" wire:model="tanggal_presentasi" type="datetime-local" />
                        <x-mary-input label="Kategori" wire:model="kategori_presentasi" placeholder="mis. Luring" />
                        <x-mary-input label="Media" wire:model="media_presentasi" placeholder="mis. Zoom / R. Rapat" />
                    </div>
                    <x-slot:actions>
                        {{-- Loloskan juga tersedia setelah verifikasi revisi: revisi yang sudah
                             memuaskan tak perlu presentasi ulang hanya demi mencapai tombol ini. --}}
                        @if (in_array($proposal->status, [ProposalStatus::MenungguPresentasi, ProposalStatus::MenungguVerifikasiRevisi], true))
                            <x-mary-button label="Loloskan ke KEPK" wire:click="loloskan" class="btn-success" spinner />
                        @endif
                        @if ($proposal->status !== ProposalStatus::MenungguPresentasi)
                            <x-mary-button label="Minta Presentasi" wire:click="mintaPresentasi" class="btn-info" spinner />
                        @endif
                        <x-mary-button label="Minta Revisi" wire:click="mintaRevisi" class="btn-warning" spinner />
                        <x-mary-button label="Tolak" wire:click="tolak" class="btn-error" spinner
                            wire:confirm="Yakin menolak proposal ini? Wajib melampirkan surat penolakan." />
                    </x-slot:actions>
                </x-mary-card>
            @endif

            @if ($isCru && $proposal->status === ProposalStatus::MenungguVerifikasiPembayaran)
                <x-mary-card title="Verifikasi Pembayaran" subtitle="Terima → unggah draft izin. Tolak → kembali ke peneliti." shadow>
                    <x-mary-textarea label="Catatan" wire:model="catatan" rows="2" />
                    <x-mary-file label="Draft surat izin (PDF)" wire:model="fileUpload" accept="application/pdf" />
                    <x-slot:actions>
                        <x-mary-button label="Tolak Bukti Bayar" wire:click="tolakBuktiBayar" class="btn-warning" spinner />
                        <x-mary-button label="Terbitkan Draft Izin" wire:click="terbitkanDraftIzin" class="btn-success" spinner />
                    </x-slot:actions>
                </x-mary-card>
            @endif

            @if ($isCru && $proposal->status === ProposalStatus::MenungguVerifikasiAkhir)
                <x-mary-card title="Verifikasi Akhir" subtitle="Terima → terbit izin final (unduh terkunci survey). Tolak → laporan diperbaiki." shadow>
                    <x-mary-textarea label="Catatan" wire:model="catatan" rows="2" />
                    <x-mary-file label="Surat izin final (PDF)" wire:model="fileUpload" accept="application/pdf" />
                    <x-slot:actions>
                        <x-mary-button label="Tolak Laporan" wire:click="tolakLaporan" class="btn-warning" spinner />
                        <x-mary-button label="Terbitkan Izin Final" wire:click="terbitkanIzinFinal" class="btn-success" spinner />
                    </x-slot:actions>
                </x-mary-card>
            @endif

            @if ($isCru && ! $proposal->status->isTerminal())
                <x-mary-card shadow>
                    <x-slot:actions>
                        <x-mary-button label="Batalkan Proposal" wire:click="batalkan" class="btn-error btn-outline btn-sm" spinner
                            wire:confirm="Batalkan proposal ini secara permanen?" />
                    </x-slot:actions>
                </x-mary-card>
            @endif

            {{-- ===== PANEL AKSI REVIEWER (jawaban ke KEPK) ===== --}}
            @if ($isReviewer && $penugasanSaya && $proposal->status === ProposalStatus::MenungguReviewReviewer)
                @if ($penugasanSaya->status === 'menunggu')
                    <x-mary-card title="Telaah Reviewer" subtitle="Tanggapan Anda dikirim ke KEPK (bukan langsung ke peneliti)" shadow>
                        <x-mary-textarea label="Komentar / masukan" wire:model="catatan" rows="3" />
                        {{-- File tanggapan jarang dipakai — disembunyikan di balik toggle --}}
                        <details class="mt-2">
                            <summary class="text-sm opacity-60 cursor-pointer select-none">Lampirkan file tanggapan (opsional)</summary>
                            <div class="pt-2">
                                <x-mary-file label="File tanggapan (PDF)" wire:model="fileUpload" accept="application/pdf" />
                            </div>
                        </details>
                        <x-slot:actions>
                            <x-mary-button label="Minta Revisi" wire:click="reviewerMintaRevisi" class="btn-warning" spinner />
                            <x-mary-button label="ACC Berkas" wire:click="reviewerAcc" class="btn-success" spinner />
                        </x-slot:actions>
                    </x-mary-card>
                @else
                    <x-mary-alert icon="o-check" class="alert-success">
                        Tanggapan Anda ({{ strtoupper($penugasanSaya->status) }}) sudah terkirim ke KEPK. Menunggu reviewer lain / keputusan KEPK.
                    </x-mary-alert>
                @endif
            @endif

            {{-- ===== PANEL AKSI KEPK ===== --}}
            @if ($isKepk && $proposal->status === ProposalStatus::MenungguPenunjukanReviewer)
                <x-mary-card title="Penunjukan Reviewer" subtitle="Pilih minimal 1 reviewer untuk menelaah berkas etik" shadow>
                    <x-mary-choices-offline label="Reviewer" wire:model="reviewerTerpilih"
                        :options="$reviewerOptions" placeholder="Pilih reviewer..." searchable />
                    <x-mary-textarea label="Catatan (opsional)" wire:model="catatan" rows="2" />
                    <x-slot:actions>
                        <x-mary-button label="Tolak Kaji Etik" wire:click="kepkTolak" class="btn-error btn-outline" spinner
                            wire:confirm="Tolak secara etik? Status ini terminal." />
                        <x-mary-button label="Tugaskan Reviewer" wire:click="tugaskanReviewer" icon="o-user-plus" class="btn-primary" spinner />
                    </x-slot:actions>
                </x-mary-card>
            @endif

            @if ($isKepk && in_array($proposal->status, [ProposalStatus::MenungguReviewReviewer, ProposalStatus::DisetujuiReviewer], true))
                <x-mary-card title="Keputusan KEPK" subtitle="Rekap tanggapan reviewer — identitas reviewer tidak diteruskan ke peneliti" shadow>
                    <table class="table table-sm mb-3">
                        <thead><tr><th>Reviewer</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($assignments as $a)
                            <tr>
                                <td>{{ $a->reviewer?->name }}</td>
                                <td><span class="badge badge-sm {{ ['menunggu' => 'badge-neutral', 'acc' => 'badge-success', 'revisi' => 'badge-warning'][$a->status] }}">{{ $a->status }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @php
                        $adaRevisi = $assignments->contains('status', 'revisi');
                        $masihMenunggu = $assignments->contains('status', 'menunggu');
                    @endphp

                    @if ($masihMenunggu && ! $adaRevisi)
                        <x-mary-alert icon="o-clock" class="alert-info mb-2">
                            Menunggu tanggapan reviewer. Keputusan (teruskan revisi / lanjut) mengikuti hasil review.
                        </x-mary-alert>
                    @endif

                    <x-mary-textarea label="Catatan untuk peneliti / alasan" wire:model="catatan" rows="2"
                        hint="Saat meneruskan revisi, rangkum masukan reviewer di sini — nama reviewer jangan disebut." />
                    <x-mary-file label="Surat tanggapan resmi untuk peneliti (PDF, opsional)" wire:model="fileUpload" accept="application/pdf" />

                    <x-slot:actions>
                        @if ($adaRevisi && $proposal->status === ProposalStatus::MenungguReviewReviewer)
                            <x-mary-button label="Teruskan Revisi ke Peneliti" wire:click="kepkTeruskanRevisi" class="btn-warning" spinner />
                        @endif
                        @if ($proposal->semuaReviewerAcc())
                            <x-mary-button label="Lanjut ke Pembayaran" wire:click="kepkLanjut" class="btn-success" spinner />
                        @endif
                        <x-mary-button label="Tolak Kaji Etik" wire:click="kepkTolak" class="btn-error btn-outline" spinner
                            wire:confirm="Tolak secara etik? Status ini terminal." />
                    </x-slot:actions>
                </x-mary-card>
            @endif

            @if ($isKepk && $proposal->status === ProposalStatus::MenungguKelengkapanBerkasEtik)
                <x-mary-card title="Keputusan KEPK" shadow>
                    <x-mary-textarea label="Alasan" wire:model="catatan" rows="2" />
                    <x-slot:actions>
                        <x-mary-button label="Tolak Kaji Etik" wire:click="kepkTolak" class="btn-error" spinner
                            wire:confirm="Tolak secara etik? Status ini terminal." />
                    </x-slot:actions>
                </x-mary-card>
            @endif
        </div>

        {{-- Kolom kanan: riwayat --}}
        <div class="space-y-6">
            @if ($bolehLihatReview && $reviews->isNotEmpty())
                <x-mary-card title="Komentar Reviewer" subtitle="Tidak terlihat oleh peneliti" shadow>
                    @foreach ($reviews as $r)
                        <div class="py-2 border-b border-base-200 last:border-0 text-sm">
                            <div class="flex justify-between">
                                <span class="font-medium">Ronde {{ $r->ronde }} · {{ strtoupper($r->keputusan) }}</span>
                                <span class="text-xs opacity-60">{{ $r->created_at->format('d/m/Y') }}</span>
                            </div>
                            <div class="text-xs opacity-60">{{ $r->reviewer?->name }}</div>
                            @if ($r->komentar)<div class="mt-1">{{ $r->komentar }}</div>@endif
                        </div>
                    @endforeach
                </x-mary-card>
            @endif

            <x-mary-card title="Riwayat Status" shadow>
                <ul class="timeline timeline-vertical timeline-compact">
                    @foreach ($history as $h)
                        <li>
                            @if (! $loop->first)<hr>@endif
                            <div class="timeline-middle"><x-mary-icon name="o-check-circle" class="w-4 h-4 text-primary" /></div>
                            <div class="timeline-end text-xs pb-3">
                                <div class="font-medium">{{ $h->to_status->value }}</div>
                                {{-- Kerahasiaan: nama reviewer disamarkan bagi yang tak berwenang --}}
                                <div class="opacity-60">{{ $h->created_at->format('d/m/Y H:i') }} ·
                                    {{ ! $bolehLihatReview && $h->actor?->hasRole('reviewer') ? 'Reviewer' : ($h->actor?->name ?? 'Sistem') }}</div>
                                @if ($h->catatan)<div class="italic opacity-80">{{ $h->catatan }}</div>@endif
                            </div>
                            @if (! $loop->last)<hr>@endif
                        </li>
                    @endforeach
                </ul>
            </x-mary-card>
        </div>
    </div>
</div>

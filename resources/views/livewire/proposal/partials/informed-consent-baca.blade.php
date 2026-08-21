{{--
    Informed Consent versi baca (isi modal di kartu Dokumen).

    $formulir = App\Models\InformedConsent
--}}
@php
    use App\Enums\BagianLembarInformasi;
@endphp

<div class="space-y-5 text-sm">
    @if (! $formulir->merekrut_partisipan)
        <div>
            <div class="font-semibold mb-1">Tidak merekrut partisipan secara langsung</div>
            <div class="whitespace-pre-line">{{ $formulir->alasan_tanpa_consent ?: '—' }}</div>
            <div class="text-xs opacity-60 mt-2">
                Karena itu Lembar Informasi tidak diisi dan tidak ada Lembar Persetujuan yang perlu dicetak.
            </div>
        </div>
    @else
        <div>
            <div class="font-semibold mb-2">Lembar Informasi</div>
            <div class="bg-base-200 rounded-lg p-3">
                Saya adalah {{ $formulir->peran_peneliti ?: '—' }} yang berasal dari
                {{ $proposal->institusi_asal ?: '—' }} yang sedang melakukan penelitian untuk
                {{ $formulir->maksud_penelitian ?: '—' }}.
            </div>
        </div>

        <div class="space-y-3">
            @foreach (BagianLembarInformasi::cases() as $bagian)
                <div>
                    <div class="font-medium">{{ $bagian->label() }}</div>
                    <div class="whitespace-pre-line">{{ $formulir->bagian($bagian) ?: '—' }}</div>
                </div>
            @endforeach
        </div>

        @include('livewire.proposal.partials.tanda-tangan-baca', [
            'tandaTangan' => $formulir->tanda_tangan,
            'tanggal' => $formulir->dikirim_pada,
        ])
    @endif
</div>

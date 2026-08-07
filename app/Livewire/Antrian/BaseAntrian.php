<?php

namespace App\Livewire\Antrian;

use App\Enums\Unit;
use App\Models\Proposal;
use Livewire\Component;

abstract class BaseAntrian extends Component
{
    /** Infinite scroll: jumlah baris yang dimuat, bertambah saat sentinel terlihat. */
    public int $perPage = 15;

    public string $cari = '';

    /** antrian = butuh aksi sekarang; riwayat = semua yang pernah lewat unit ini. */
    public string $tab = 'antrian';

    abstract protected function unit(): Unit;

    abstract protected function judul(): string;

    /** Query dasar antrian — subclass boleh override (mis. reviewer per penugasan). */
    protected function query()
    {
        return Proposal::query()->where('unit_sekarang', $this->unit()->value);
    }

    /** Riwayat: seluruh proposal yang pernah melewati unit ini (untuk pemantauan). */
    protected function riwayatQuery()
    {
        return Proposal::query()->whereHas('statusHistory', fn ($q) => $q
            ->where('unit', $this->unit()->value));
    }

    public function updatedTab(): void
    {
        $this->perPage = 15;
    }

    public function updatedCari(): void
    {
        $this->perPage = 15;
    }

    /** Dipanggil sentinel x-intersect saat pengguna scroll mendekati bawah. */
    public function muatLagi(): void
    {
        $this->perPage += 15;
    }

    public function render()
    {
        $riwayat = $this->tab === 'riwayat';

        $query = ($riwayat ? $this->riwayatQuery() : $this->query())
            ->when($this->cari, fn ($q) => $q->where(fn ($w) => $w
                ->where('kode', 'ilike', "%{$this->cari}%")
                ->orWhere('judul_penelitian', 'ilike', "%{$this->cari}%")
                ->orWhere('peneliti_utama', 'ilike', "%{$this->cari}%")))
            // Terbaru selalu di atas, di kedua tab.
            ->orderByDesc('updated_at');

        $total = (clone $query)->count();
        $proposals = $query->take($this->perPage)->get();

        return view('livewire.antrian.index', [
            'proposals' => $proposals,
            'adaLagi' => $total > $proposals->count(),
            'judul' => $this->judul(),
            'riwayat' => $riwayat,
        ])->title($this->judul());
    }
}

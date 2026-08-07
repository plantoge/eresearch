<?php

namespace App\Livewire\Antrian;

use App\Enums\ProposalStatus;
use App\Enums\Unit;
use App\Models\Proposal;

class Reviewer extends BaseAntrian
{
    protected function unit(): Unit
    {
        return Unit::Reviewer;
    }

    protected function judul(): string
    {
        return 'Antrian Reviewer';
    }

    /** Hanya proposal yang DITUGASKAN ke reviewer ini dan belum ia respons. */
    protected function query()
    {
        return Proposal::query()
            ->where('status', ProposalStatus::MenungguReviewReviewer->value)
            ->whereHas('penugasanReviewer', fn ($q) => $q
                ->where('reviewer_id', auth()->id())
                ->where('status', 'menunggu'));
    }

    /** Riwayat: semua proposal yang pernah ditugaskan ke reviewer ini. */
    protected function riwayatQuery()
    {
        return Proposal::query()
            ->whereHas('penugasanReviewer', fn ($q) => $q
                ->where('reviewer_id', auth()->id()));
    }
}

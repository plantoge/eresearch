<?php

namespace App\Livewire\Forms\Concerns;

/**
 * Jawaban ya/tidak yang punya keadaan ketiga: belum dijawab.
 *
 * Radio HTML mengirim string, dan properti bool tidak bisa membedakan "tidak"
 * dari "belum dijawab" — keduanya jadi false, sehingga `required` tidak pernah
 * menangkap pertanyaan yang dilewati. Karena itu form menyimpan '1'/'0'/'' dan
 * hanya menerjemahkannya ke bool tepat saat disimpan.
 */
trait JawabanYaTidak
{
    /** Jawaban bernilai "ya"? */
    protected function ya(string $kunci): bool
    {
        return $this->{$kunci} === '1';
    }

    /** Jawaban sudah diisi (bukan '')? */
    protected function terjawab(string $kunci): bool
    {
        return $this->{$kunci} !== '';
    }

    /** '1'/'0'/'' untuk form => bool/null untuk database. */
    protected function keDatabase(string $kunci): ?bool
    {
        return $this->terjawab($kunci) ? $this->ya($kunci) : null;
    }

    /** bool/null dari database => '1'/'0'/'' yang dipakai radio di form. */
    protected static function keForm(?bool $nilai): string
    {
        return $nilai === null ? '' : ($nilai ? '1' : '0');
    }
}

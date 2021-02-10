<?php

namespace App\Http\Livewire;

use App\DokumenWord;
use Livewire\Component;

class DokumenWordShow extends Component
{
    public DokumenWord $dokumenWord;

    public function render()
    {
        return view('livewire.dokumen-word-show', [
            "dokumen_word" => $this->dokumenWord
        ]);
    }
}

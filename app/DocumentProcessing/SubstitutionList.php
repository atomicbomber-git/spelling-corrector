<?php


namespace App\DocumentProcessing;


class SubstitutionList
{
    public array $entries = [];

    public function addEntry(string $old, int $index, string $new): void
    {
        $this->entries[$old][$index] ??= $new;
    }

    public function getSubstitutionFor(string $old, int $index): ?string
    {
        return $this->entries[$old][$index] ?? null;
    }
}
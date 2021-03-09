<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FrekuensiBigram extends Model
{
    protected $table = "frekuensi_bigram";
    protected $guarded = [];
    public $timestamps = false;
    use HasFactory;
}

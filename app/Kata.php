<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Kata extends Model
{
    protected $table = "kata";
    protected $primaryKey = "teks";
    public $incrementing = false;
    protected $perPage = 45;
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Word extends Model
{
    protected $primaryKey = "content";
    public $incrementing = false;
    protected $perPage = 45;
}

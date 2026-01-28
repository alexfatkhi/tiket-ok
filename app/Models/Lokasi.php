<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lokasi extends Model
{
    protected $table = 'lokasi';

    protected $fillable = ['nama_lokasi'];

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}

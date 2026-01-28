<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'judul',
        'deskripsi',
        'tanggal_waktu',
        'lokasi',
        'kategori_id',
        'gambar',
        'user_id',
    ];

    protected $casts = [
        'tanggal_waktu' => 'datetime',
    ];

    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }

    public function tikets()
    {
        return $this->hasMany(Tiket::class);    
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

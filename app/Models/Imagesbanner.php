<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Imagesbanner extends Model
{
    protected $table = 'images_banner';
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'ImgCls',
        'ImgFam',
        'ImgSFam',
        'ImgNom',
    ];

    public function sousfamille()
    {
        return $this->hasManyThrough(
            SouFamilles::class,
            Familles::class,
            'FamNo',      // Foreign key on families table
            'SouFam',     // Foreign key on souFamilles table
            'ImgFam',     // Local key on imagesbanner
            'FamNo'       // Local key on families
        );
    }

    public function famille()
    {
        return $this->belongsTo(Familles::class, 'ImgFam', 'FamNo');
    }
}

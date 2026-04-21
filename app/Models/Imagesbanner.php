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

    public function sousfamilles()
    {
        return $this->belongsTo(SouFamilles::class, 'ImgSFam', 'SouNo');
    }
}

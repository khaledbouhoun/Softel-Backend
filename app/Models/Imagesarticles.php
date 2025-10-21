<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Imagesarticles extends Model
{
    protected $table = 'images_articles';
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'ImgCls',
        'ImgArt',
        'ImgNom',
    ];

   
    public function company()
    {
        return $this->belongsTo(Company::class, 'ImgCls', 'ClsNo');
    }
    

    public function product()
    {
        return $this->belongsTo(Product::class, 'ImgArt', 'ArtNo');
    }
}

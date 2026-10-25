<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SouFamilles extends Model
{

    protected $table = 'stk_sfamilles';

    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;

    protected $fillable = [

        'SouCls',
        'SouFam',
        'SouNo',
        'SouNom',
        'SouImg',
    ];




    public function company()
    {
        return $this->belongsTo(Company::class, 'SouCls', 'ClsNo');
    }

    public function famille()
    {
        return $this->belongsTo(Familles::class, 'SouFam', 'FamNo');
    }


    public function products()
    {
        return $this->hasMany(Product::class, 'ArtSFam', 'SouNo');
    }

    public function imagesBanner()
    {
        return $this->hasMany(Imagesbanner::class, 'ImgSFam', 'SouNo');
    }
}

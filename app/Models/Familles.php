<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Familles extends Model
{
    protected $table = 'stk_familles';

    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;



    protected $fillable = [
        'FamCls',
        'FamNo',
        'FamNom',
        'FamImg',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'FamCls', 'ClsNo');
    }

    public function souFamilles()
    {
        return $this->hasMany(SouFamilles::class, 'SouFam', 'FamNo');
    }


    public function produits()
    {
        return $this->hasMany(Product::class, 'ArtFam', 'FamNo');
    }
}

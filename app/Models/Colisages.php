<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Colisages extends Model
{

    protected $table = 'stk_colisages';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;
    protected $fillable = [
        'ColCls',
        'ColNo',
        'ColNom',
        'ColABV',
    ];


    public function company()
    {
        return $this->belongsTo(Company::class, 'ColCls', 'ClsNo');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'ArtColis', 'ColNo');
    }
}

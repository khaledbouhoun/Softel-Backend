<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unites extends Model
{
    protected $table = 'stk_unites';

    public $incrementing = false;
    public $timestamps = false;
      protected $primaryKey = null;


    protected $fillable = [
        'UniCls',
        'UniNo',
        'UniABV',
        'UniNom',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'UniCls', 'ClsNo');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'ArtUni', 'UniNo');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commune extends Model
{
    protected $table = 'sys_villes';

    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;

    protected $fillable = [
        'VilPays',
        'VilTerr',
        'VilNo',
        'VilNom',
    ];

    public function wilaya()
    {
        return $this->belongsTo(Wilaya::class, 'VilTerr', 'TerNo');
    }
}

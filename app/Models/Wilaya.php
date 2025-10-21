<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wilaya extends Model
{
     protected $table = 'sys_territoires';

    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;

    protected $fillable = [
        'TerPays',
        'TerNo',
        'TerNom',
    ];

    public function communes()
    {
        return $this->hasMany(Commune::class, 'VilTerr', 'TerNo');
    }

}

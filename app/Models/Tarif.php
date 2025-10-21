<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tarif extends Model
{

    protected $table = 'vte_tarifd';
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'TfdCls',
        'TfdArt',
        'TfdTfe',
        'TfdPrixTTC',
    ];

    protected $casts = [
        'TfdPrixTTC' => 'decimal:2',
    ];
    public function company()
    {
        return $this->belongsTo(Company::class, 'TfdCls', 'ClsNo');
    }
    

    public function product()
    {
        return $this->belongsTo(Product::class, 'TfdArt', 'ArtNo');
    }
}

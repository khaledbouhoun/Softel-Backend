<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommandeDetail extends Model
{
    protected $table = 'vte_commandesd';
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'CddID';

    protected $fillable = [
        'CddID',
        'CddArt',
        'CddColisage',
        'CddColis',
        'CddUntCol',
        'CddQte',
        'CddPrix',
        'CddMontant'
    ];

    protected $casts = [
        'CddColis' => 'decimal:2',
        'CddUntCol' => 'decimal:2',
        'CddQte' => 'decimal:2',
        'CddPrix' => 'decimal:2',
        'CddMontant' => 'decimal:2',
    ];

    public function product($Cls = null)
    {
        return $this->belongsTo(Product::class, 'CddArt', 'ArtNo')->where('ArtCls', $Cls);
    }


    public function commandeHead()
    {
        return $this->belongsTo(CommandeHead::class, 'CddID', 'CdeID');
    }
}

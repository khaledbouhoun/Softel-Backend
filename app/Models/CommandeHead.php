<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommandeHead extends Model
{
    protected $table = 'vte_commandese';
    public $timestamps = false;
    public $incrementing = true;
    protected $primaryKey = 'CdeID';
    protected $keyType = 'int';

    protected $fillable = [
        'CdeCls',
        'CdeCli',
        'CdeDate',
        'CdeStatus',
        'CdeMontant'

    ];
    protected $casts = [
        'CdeID' => 'integer',
        'CdeStatus' => 'integer',
        'CdeDate' => 'date:Y-m-d H:i:s',
        'CdeMontant' => 'decimal:2',
    ];

    public function details()
    {
        return $this->hasMany(CommandeDetail::class, 'CddID', 'CdeID');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'CdeCli', 'CliNo');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'CdeCls', 'ClsNo');
    }
}

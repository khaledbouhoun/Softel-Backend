<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'vte_clients_softel';
    protected $primaryKey = 'ClsNo';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'ClsNo',
        'ClsNom',
        'ClsClr1',
        'ClsClr2',
        'ClsImg',
        'ClsActif',
        'ClsDateIn',
        'ClsDateOut'
    ];
    protected $casts = [
        'ClsDateIn' => 'date',
        'ClsDateOut' => 'date',
    ];


    public function clients()
    {
        return $this->hasMany(ClientsNew::class, 'CliCls', 'ClsNo');
    }
    public function products()
    {
        return $this->hasMany(Product::class, 'ArtCls', 'ClsNo');
    }
    public function familles()
    {
        return $this->hasMany(Familles::class, 'FamCls', 'ClsNo');
    }
    public function unites()
    {
        return $this->hasMany(Unites::class, 'UniCls', 'ClsNo');
    }
    public function colisages()
    {
        return $this->hasMany(Colisages::class, 'ColCls', 'ClsNo');
    }
    public function sousFamilles()
    {
        return $this->hasMany(SouFamilles::class, 'SouCls', 'ClsNo');
    }
    

}

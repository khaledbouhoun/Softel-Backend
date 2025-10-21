<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;




class Client extends Authenticatable
{
    use HasApiTokens;


    protected $table = 'vte_clients';
    protected $primaryKey = 'CliNo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'CliCls',
        'CliNo',
        'CliNom',
        'CliTel1',
        'CliEmail',
        'CliGoogleId',
        'CliActif',
        'CliTfe',
    ];

    protected $hidden = [
        'CliGoogleId',
    ];

    public function getAuthIdentifier()
    {
        return $this->CliCls . '-' . $this->CliNo;
    }



    public function getAuthPassword()
    {
        return $this->CliPass;
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'CliCls', 'ClsNo');
    }
}

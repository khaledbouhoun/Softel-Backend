<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class ClientsNew extends Model
{
    use HasFactory;
    use HasApiTokens;

    protected $table = 'vte_clients_new';
    protected $primaryKey = 'Cliid';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'CliCls',
        'CliNom',
        'CliGoogleId',
        'CliTel',
        'CliEmail',
        'CliWilaya',
        'CliCommune',
        'CliActif',
        'CliDate',
    ];

    // protected $hidden = [
    //     'CliGoogleId',
    // ];

    protected $casts = [
        'CliDate' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'CliCls', 'ClsNo');
    }
}

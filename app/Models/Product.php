<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'stk_articles';

    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;


    protected $fillable = [
        'ArtCls',
        'ArtNo',
        'ArtNom',
        'ArtFam',
        'ArtSFam',
        'ArtUni',
        'ArtColis',
        'ArtUntCol',
        'ArtPrix',
    ];
    protected $casts = [
        'ArtUntCol' => 'decimal:2',
        'ArtPrix' => 'decimal:2',
    ];


    public function company()
    {
        return $this->belongsTo(Company::class, 'ArtCls', 'ClsNo');
    }


    public function familles($Cls = null)
    {
        return $this->belongsTo(Familles::class, 'ArtFam', 'FamNo')
            ->where('FamCls', $Cls);
    }


    public function sousfamilles($familles = null, $Cls = null)
    {
        if ($familles) {
            return $this->belongsTo(SouFamilles::class, 'ArtSFam', 'SouNo')
                ->where('SouFam', $familles)->where('SouCls', $Cls);
        }
        return $this->belongsTo(SouFamilles::class, 'ArtSFam', 'SouNo')->where('SouCls', $Cls);
    }


    public function cloisage($Cls = null)
    {
        return $this->belongsTo(Colisages::class, 'ArtColis', 'ColNo')->where('ColCls', $Cls);
    }


    public function unite($Cls = null)
    {
        return $this->belongsTo(Unites::class, 'ArtUni', 'UniNo')->where('UniCls', $Cls);
    }


    public function tarifs($Cls = null)
    {
        return $this->hasMany(Tarif::class, 'TfdArt', 'ArtNo')->where('TfdCls', $Cls);
    }

    public function imagesarticles($Cls = null)
    {
        return $this->hasMany(Imagesarticles::class, 'ImgArt', 'ArtNo')->where('ImgCls', $Cls);
    }


    public function priceForClient($client)
    {
        $tarif = $this->tarifs($client->CliCls)
            ->where('TfdTfe', $client->CliTfe)
            ->first();

        return $tarif ? (float) $tarif->TfdPrixTTC : (float) $this->ArtPrix;
    }
}

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
        'ArtPrix'   => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS (NO PARAMETERS ✅)
    |--------------------------------------------------------------------------
    */

    public function company()
    {
        return $this->belongsTo(Company::class, 'ArtCls', 'ClsNo');
    }

    public function familles()
    {
        return $this->belongsTo(Familles::class, 'ArtFam', 'FamNo');
    }

    public function sousfamilles()
    {
        return $this->belongsTo(SouFamilles::class, 'ArtSFam', 'SouNo');
    }

    public function cloisage()
    {
        return $this->belongsTo(Colisages::class, 'ArtColis', 'ColNo');
    }

    public function unite()
    {
        return $this->belongsTo(Unites::class, 'ArtUni', 'UniNo');
    }

    public function imagesarticles()
    {
        return $this->hasMany(Imagesarticles::class, 'ImgArt', 'ArtNo');
    }

    public function tarifs()
    {
        return $this->hasMany(Tarif::class, 'TfdArt', 'ArtNo');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES (FILTER BY CLIENT ✅)
    |--------------------------------------------------------------------------
    */

    public function scopeForClient($query, $client)
    {
        return $query->where('ArtCls', $client->CliCls);
    }

    public function scopeWithClientRelations($query, $client)
    {
        return $query->with([
            'familles' => fn($q) => $q->where('FamCls', $client->CliCls),
            'sousfamilles' => fn($q) => $q->where('SouCls', $client->CliCls),
            'cloisage' => fn($q) => $q->where('ColCls', $client->CliCls),
            'unite' => fn($q) => $q->where('UniCls', $client->CliCls),
            'imagesarticles' => fn($q) => $q->where('ImgCls', $client->CliCls),
            'tarifs' => fn($q) => $q->where('TfdCls', $client->CliCls),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC
    |--------------------------------------------------------------------------
    */

    public function priceForClient($client)
    {
        // IMPORTANT: use already loaded tarifs (no query)
        $tarifs = $this->tarifs;

        if ($tarifs->isEmpty()) {
            return (float) $this->ArtPrix;
        }

        if (!empty($client->CliTarif)) {
            $price = $tarifs
                ->where('TfdTfe', $client->CliTarif)
                ->first();

            if ($price) {
                return (float) $price->TfdPrixTTC;
            }
        }

        return (float) ($tarifs->first()->TfdPrixTTC ?? $this->ArtPrix);
    }
}

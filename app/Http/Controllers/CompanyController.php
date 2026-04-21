<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::where('ClsActif', 1)->get();

        $companies->transform(function ($company) {
            $img = $company->getRawOriginal('ClsImg');

            $company->ClsImg = $img
                ? url('images/clients_softel/' . $img)
                : url('images/default.png');

            return $company;
        });

        return response()->json($companies);
    }
}

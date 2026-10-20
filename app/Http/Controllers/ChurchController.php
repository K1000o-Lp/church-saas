<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\ChurchMembership;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChurchController extends Controller
{
    public function show(Request $request, Church $church): View
    {
        /** @var ChurchMembership $membership */
        $membership = $request->attributes->get('currentMembership');

        return view('churches.show', [
            'church' => $church,
            'membership' => $membership,
        ]);
    }
}

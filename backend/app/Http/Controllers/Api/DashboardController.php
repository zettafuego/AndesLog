<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardQuery;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardQuery $dashboard)
    {
        $operator = $request->attributes->get('operator');

        return response()->json($dashboard->forWarehouse($operator->warehouse_id));
    }
}

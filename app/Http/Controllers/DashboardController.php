<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Branch;

class DashboardController extends Controller
{
    public function index()
    {
        $totalSalesCount = Sale::count();
        $totalSalesAmount = Sale::sum('total');
        $totalPaidAmount = Sale::sum('paid_amount');
        $totalUnpaidAmount = $totalSalesAmount - $totalPaidAmount;

        $customersCount = Customer::count();
        $latestSales = Sale::with(['customer', 'organization'])->latest()->take(5)->get();

        return view('dashboard', compact(
            'totalSalesCount',
            'totalSalesAmount',
            'totalPaidAmount',
            'totalUnpaidAmount',
            'customersCount',
            'latestSales'
        ));
    }
}

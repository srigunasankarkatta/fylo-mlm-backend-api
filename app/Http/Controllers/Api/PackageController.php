<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $q = Package::query()->where('is_active', true);

        if ($request->filled('min_price')) $q->where('price', '>=', $request->min_price);
        if ($request->filled('max_price')) $q->where('price', '<=', $request->max_price);

        $packages = $q->orderBy('level_number')->paginate($request->get('per_page', 20));
        return $this->paginated($packages, 'Packages retrieved');
    }

    public function show($id)
    {
        $package = Package::find($id);
        if (!$package) return $this->notFound('Package not found');
        return $this->success($package, 'Package retrieved');
    }
}

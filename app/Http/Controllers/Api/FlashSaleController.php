<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreFlashSaleRequest;
use App\Models\FlashSale;
use Illuminate\Http\Request;

class FlashSaleController extends ApiController
{
    public function index(Request $request)
    {
        $flashSales = FlashSale::where('business_id', $this->business($request)->id)
            ->with('product')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->ok($flashSales, 'Flash sales');
    }

    public function active(Request $request)
    {
        $flashSales = FlashSale::where('business_id', $this->business($request)->id)
            ->active()
            ->with('product')
            ->orderBy('ends_at')
            ->get();

        return $this->ok($flashSales, 'Active flash sales');
    }

    public function store(StoreFlashSaleRequest $request)
    {
        $flashSale = FlashSale::create([
            ...$request->validated(),
            'business_id' => $this->business($request)->id,
        ]);

        return $this->ok($flashSale->load('product'), 'Flash sale created', 201);
    }

    public function update(StoreFlashSaleRequest $request, int $id)
    {
        $flashSale = FlashSale::where('business_id', $this->business($request)->id)->findOrFail($id);
        $this->authorize('update', $flashSale);
        $flashSale->update($request->validated());

        return $this->ok($flashSale->load('product'), 'Flash sale updated');
    }

    public function destroy(Request $request, int $id)
    {
        $flashSale = FlashSale::where('business_id', $this->business($request)->id)->findOrFail($id);
        $this->authorize('delete', $flashSale);
        $flashSale->delete();

        return $this->ok(null, 'Flash sale deleted');
    }
}

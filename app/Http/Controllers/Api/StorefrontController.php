<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Storefront;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StorefrontController extends ApiController
{
    public function show(Request $request)
    {
        $business = $this->business($request);
        $storefront = Storefront::firstOrCreate(
            ['business_id' => $business->id],
            [
                'subdomain' => $business->slug,
                'store_name' => $business->name,
                'store_description' => $business->address,
                'contact_number' => $business->phone,
            ]
        );

        return $this->ok($storefront, 'Storefront settings');
    }

    public function update(Request $request)
    {
        $business = $this->business($request);
        $data = $request->validate([
            'subdomain' => ['sometimes', 'alpha_dash', 'min:3', 'max:60'],
            'store_name' => ['sometimes', 'string', 'max:180'],
            'store_description' => ['nullable', 'string'],
            'theme_color' => ['nullable', 'regex:/^#?[A-Fa-f0-9]{6}$/'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'url'],
            'social_links.instagram' => ['nullable', 'url'],
            'social_links.whatsapp' => ['nullable', 'string'],
            'social_links.youtube' => ['nullable', 'url'],
            'settings_json' => ['nullable', 'array'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'featured_product_ids' => ['nullable', 'array'],
            'featured_product_ids.*' => ['integer'],
            'hidden_product_ids' => ['nullable', 'array'],
            'hidden_product_ids.*' => ['integer'],
        ]);

        $storefront = Storefront::firstOrCreate(
            ['business_id' => $business->id],
            [
                'subdomain' => $business->slug,
                'store_name' => $business->name,
            ]
        );

        if (isset($data['subdomain'])) {
            $storefront->subdomain = strtolower($data['subdomain']);
        }

        if (isset($data['theme_color'])) {
            $data['theme_color'] = str_starts_with($data['theme_color'], '#') ? $data['theme_color'] : '#' . $data['theme_color'];
        }

        $storefront->fill(collect($data)->except(['featured_product_ids', 'hidden_product_ids'])->all());
        $storefront->save();

        if (array_key_exists('featured_product_ids', $data)) {
            Product::where('business_id', $business->id)->update(['is_featured' => false]);
            Product::where('business_id', $business->id)
                ->whereIn('id', $data['featured_product_ids'])
                ->update(['is_featured' => true]);
        }

        if (array_key_exists('hidden_product_ids', $data)) {
            Product::where('business_id', $business->id)->update(['is_published' => true]);
            Product::where('business_id', $business->id)
                ->whereIn('id', $data['hidden_product_ids'])
                ->update(['is_published' => false]);
        }

        return $this->ok($storefront->fresh(), 'Storefront updated');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => ['required', 'image', 'max:4096']]);

        $storefront = $this->business($request)->storefront ?: Storefront::firstOrCreate([
            'business_id' => $this->business($request)->id,
        ], [
            'subdomain' => $this->business($request)->slug,
            'store_name' => $this->business($request)->name,
        ]);

        if ($storefront->logo_path) {
            Storage::disk('public')->delete($storefront->logo_path);
        }

        $path = $request->file('logo')->store("businesses/{$storefront->business_id}/storefront", 'public');
        $storefront->update(['logo_path' => $path]);

        return $this->ok(['logo_path' => $path], 'Storefront logo uploaded');
    }

    public function uploadBanner(Request $request)
    {
        $request->validate(['banner' => ['required', 'image', 'max:6144']]);

        $storefront = $this->business($request)->storefront ?: Storefront::firstOrCreate([
            'business_id' => $this->business($request)->id,
        ], [
            'subdomain' => $this->business($request)->slug,
            'store_name' => $this->business($request)->name,
        ]);

        if ($storefront->banner_path) {
            Storage::disk('public')->delete($storefront->banner_path);
        }

        $path = $request->file('banner')->store("businesses/{$storefront->business_id}/storefront", 'public');
        $storefront->update(['banner_path' => $path]);

        return $this->ok(['banner_path' => $path], 'Storefront banner uploaded');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CrudController extends ApiController
{
    public function showBusiness(Request $request)
    {
        return $this->ok($this->business($request)->load(['subscription.plan', 'storefront']), 'Business profile');
    }
    public function updateBusiness(Request $request)
    {
        $data = $request->validate(['name' => ['sometimes', 'string'], 'phone' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'address' => ['nullable', 'string'], 'facebook_page_url' => ['nullable', 'url'], 'whatsapp_number' => ['nullable', 'string'], 'instagram_url' => ['nullable', 'url'], 'currency' => ['nullable', 'string'], 'timezone' => ['nullable', 'string'], 'invoice_prefix' => ['nullable', 'string'], 'invoice_footer' => ['nullable', 'string']]);
        $b = $this->business($request);
        if (isset($data['name'])) $data['slug'] = Str::slug($data['name']) . '-' . $b->id;
        $b->update($data);
        return $this->ok($b->refresh(), 'Business updated');
    }
    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => ['required', 'image', 'max:2048']]);
        $b = $this->business($request);
        if ($b->logo) Storage::disk('public')->delete($b->logo);
        $path = $request->file('logo')->store("businesses/{$b->id}/settings", 'public');
        $b->update(['logo' => $path]);
        return $this->ok(['logo' => $path], 'Logo uploaded');
    }

    public function staff(Request $request)
    {
        return $this->ok(BusinessUser::where('business_id', $this->business($request)->id)->with(['user', 'role'])->paginate($request->integer('per_page', 20)), 'Staff list');
    }
    public function addStaff(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string'], 'password' => ['nullable', 'min:8'], 'role_code' => ['required', 'exists:roles,code']]);
        if (empty($data['email']) && empty($data['phone'])) return $this->fail('Email or phone required', 422);
        $user = User::firstOrCreate(array_filter(['email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null]), ['name' => $data['name'], 'password' => Hash::make($data['password'] ?? 'password'), 'status' => 'active']);
        $role = Role::where('code', $data['role_code'])->firstOrFail();
        $this->guardOwnerStaffChange($request, $role->code);
        $this->business($request)->users()->syncWithoutDetaching([$user->id => ['role_id' => $role->id, 'status' => 'active', 'invited_by' => $request->user()->id, 'joined_at' => now(), 'updated_at' => now()]]);
        return $this->ok($user->load('businesses'), 'Staff added', 201);
    }
    public function updateStaff(Request $request, int $id)
    {
        $m = BusinessUser::where('business_id', $this->business($request)->id)->with('role')->findOrFail($id);
        $data = $request->validate(['role_code' => ['sometimes', 'exists:roles,code'], 'status' => ['sometimes', 'in:active,invited,inactive']]);
        if ($m->role?->code === 'owner') $this->guardOwnerStaffChange($request, 'owner');
        if (isset($data['role_code'])) {
            $this->guardOwnerStaffChange($request, $data['role_code']);
            $m->role_id = Role::where('code', $data['role_code'])->value('id');
        }
        if (isset($data['status'])) $m->status = $data['status'];
        $m->save();
        return $this->ok($m->load(['user', 'role']), 'Staff updated');
    }
    public function removeStaff(Request $request, int $id)
    {
        $m = BusinessUser::where('business_id', $this->business($request)->id)->with('role')->findOrFail($id);
        if ($m->role?->code === 'owner') $this->guardOwnerStaffChange($request, 'owner');
        $m->update(['status' => 'inactive']);
        return $this->ok(null, 'Staff deactivated');
    }

    public function categories(Request $request)
    {
        return $this->ok(ProductCategory::where('business_id', $this->business($request)->id)->with('products')->paginate($request->integer('per_page', 50)), 'Category list');
    }
    public function createCategory(Request $request)
    {
        $data = $request->validate(['parent_id' => ['nullable', 'integer'], 'name' => ['required', 'string'], 'description' => ['nullable', 'string'], 'status' => ['nullable', 'in:active,inactive']]);
        $data['business_id'] = $this->business($request)->id;
        $data['slug'] = Str::slug($data['name']) . '-' . uniqid();
        return $this->ok(ProductCategory::create($data), 'Category created', 201);
    }
    public function updateCategory(Request $request, int $id)
    {
        $m = ProductCategory::where('business_id', $this->business($request)->id)->findOrFail($id);
        $data = $request->validate(['parent_id' => ['nullable', 'integer'], 'name' => ['sometimes', 'string'], 'description' => ['nullable', 'string'], 'status' => ['nullable', 'in:active,inactive']]);
        if (isset($data['name'])) $data['slug'] = Str::slug($data['name']) . '-' . $m->id;
        $m->update($data);
        return $this->ok($m->refresh(), 'Category updated');
    }
    public function deleteCategory(Request $request, int $id)
    {
        ProductCategory::where('business_id', $this->business($request)->id)->findOrFail($id)->delete();
        return $this->ok(null, 'Category deleted');
    }

    public function products(Request $request)
    {
        $b = $this->business($request);
        $q = Product::where('business_id', $b->id)->with('category')->when($request->q, fn($x) => $x->where(fn($w) => $w->where('name', 'like', '%' . $request->q . '%')->orWhere('sku', 'like', '%' . $request->q . '%')))->when($request->category_id, fn($x) => $x->where('category_id', $request->category_id))->when($request->status, fn($x) => $x->where('status', $request->status))->when($request->filled('is_published'), fn($x) => $x->where('is_published', filter_var($request->is_published, FILTER_VALIDATE_BOOL)))->when($request->filled('is_featured'), fn($x) => $x->where('is_featured', filter_var($request->is_featured, FILTER_VALIDATE_BOOL)));
        return $this->ok($q->latest()->paginate($request->integer('per_page', 20)), 'Product list');
    }
    public function createProduct(Request $request)
    {
        $data = $request->validate(['category_id' => ['nullable', 'integer'], 'name' => ['required', 'string'], 'sku' => ['nullable', 'string'], 'description' => ['nullable', 'string'], 'price' => ['required', 'numeric', 'min:0'], 'cost_price' => ['nullable', 'numeric'], 'discount_price' => ['nullable', 'numeric'], 'stock_quantity' => ['nullable', 'integer'], 'low_stock_alert' => ['nullable', 'integer'], 'unit' => ['nullable', 'string'], 'image' => ['nullable'], 'status' => ['nullable', 'in:active,inactive,draft'], 'is_published' => ['nullable', 'boolean'], 'is_featured' => ['nullable', 'boolean']]);
        $b = $this->business($request);
        $data['business_id'] = $b->id;
        $data['slug'] = $this->uniqueSlug(Product::class, $b->id, $data['name']);
        $data['stock_quantity'] = $data['stock_quantity'] ?? 0;
        $data['low_stock_alert'] = $data['low_stock_alert'] ?? 5;
        $data['unit'] = $data['unit'] ?? 'pcs';
        $data['status'] = $data['status'] ?? 'active';
        $data['is_published'] = $data['is_published'] ?? true;
        $data['is_featured'] = $data['is_featured'] ?? false;
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store("businesses/{$b->id}/products", 'public');
        $p = Product::create($data);
        if ($p->stock_quantity > 0) StockMovement::create(['business_id' => $b->id, 'product_id' => $p->id, 'movement_type' => 'opening', 'quantity' => $p->stock_quantity, 'previous_stock' => 0, 'new_stock' => $p->stock_quantity, 'created_by' => $request->user()->id, 'created_at' => now()]);
        return $this->ok($p, 'Product created', 201);
    }
    public function product(Request $request, int $id)
    {
        return $this->ok(Product::where('business_id', $this->business($request)->id)->with(['category', 'images', 'stockMovements'])->findOrFail($id), 'Product details');
    }
    public function updateProduct(Request $request, int $id)
    {
        $p = Product::where('business_id', $this->business($request)->id)->findOrFail($id);
        $data = $request->validate(['category_id' => ['nullable', 'integer'], 'name' => ['sometimes', 'string'], 'sku' => ['nullable', 'string'], 'description' => ['nullable', 'string'], 'price' => ['sometimes', 'numeric', 'min:0'], 'cost_price' => ['nullable', 'numeric'], 'discount_price' => ['nullable', 'numeric'], 'low_stock_alert' => ['nullable', 'integer'], 'unit' => ['nullable', 'string'], 'image' => ['nullable', 'image', 'max:2048'], 'status' => ['nullable', 'in:active,inactive,draft'], 'is_published' => ['nullable', 'boolean'], 'is_featured' => ['nullable', 'boolean']]);
        if (isset($data['name'])) $data['slug'] = $this->uniqueSlug(Product::class, $p->business_id, $data['name'], $p->id);
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store("businesses/{$p->business_id}/products", 'public');
        $p->update($data);
        return $this->ok($p->refresh(), 'Product updated');
    }
    public function deleteProduct(Request $request, int $id)
    {
        Product::where('business_id', $this->business($request)->id)->findOrFail($id)->delete();
        return $this->ok(null, 'Product archived');
    }
    public function lowStock(Request $request)
    {
        return $this->ok(Product::where('business_id', $this->business($request)->id)->whereColumn('stock_quantity', '<=', 'low_stock_alert')->get(), 'Low stock products');
    }
    public function allStockMovements(Request $request)
    {
        return $this->ok(StockMovement::where('business_id', $this->business($request)->id)->with('product')->latest('created_at')->paginate($request->integer('per_page', 20)), 'Stock movements');
    }
    public function stockMovements(Request $request, int $id)
    {
        $p = Product::where('business_id', $this->business($request)->id)->findOrFail($id);
        return $this->ok($p->stockMovements()->latest('created_at')->paginate(20), 'Stock movements');
    }
    public function adjustStock(Request $request, int $id)
    {
        $data = $request->validate(['quantity' => ['required', 'integer'], 'movement_type' => ['required', 'in:opening,restock,adjustment,damage'], 'note' => ['nullable', 'string']]);
        $p = Product::where('business_id', $this->business($request)->id)->findOrFail($id);
        $prev = $p->stock_quantity;
        $new = max(0, $prev + (int)$data['quantity']);
        $p->update(['stock_quantity' => $new]);
        $m = StockMovement::create(['business_id' => $p->business_id, 'product_id' => $p->id, 'movement_type' => $data['movement_type'], 'quantity' => $data['quantity'], 'previous_stock' => $prev, 'new_stock' => $new, 'reference_type' => 'manual', 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id, 'created_at' => now()]);
        return $this->ok($m, 'Stock adjusted');
    }

    public function customers(Request $request)
    {
        return $this->ok(Customer::where('business_id', $this->business($request)->id)->when($request->q, fn($q) => $q->where(fn($w) => $w->where('name', 'like', '%' . $request->q . '%')->orWhere('phone', 'like', '%' . $request->q . '%')))->latest()->paginate($request->integer('per_page', 20)), 'Customer list');
    }
    public function createCustomer(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string'], 'phone' => ['required', 'string'], 'email' => ['nullable', 'email'], 'address' => ['nullable', 'string'], 'area' => ['nullable', 'string'], 'city' => ['nullable', 'string'], 'note' => ['nullable', 'string'], 'status' => ['nullable', 'in:active,blocked']]);
        $data['business_id'] = $this->business($request)->id;
        $data['status'] = $data['status'] ?? 'active';
        return $this->ok(Customer::create($data), 'Customer created', 201);
    }
    public function customer(Request $request, int $id)
    {
        return $this->ok(Customer::where('business_id', $this->business($request)->id)->with(['addresses', 'orders.items'])->findOrFail($id), 'Customer details');
    }
    public function updateCustomer(Request $request, int $id)
    {
        $c = Customer::where('business_id', $this->business($request)->id)->findOrFail($id);
        $data = $request->validate(['name' => ['sometimes', 'string'], 'phone' => ['sometimes', 'string'], 'email' => ['nullable', 'email'], 'address' => ['nullable', 'string'], 'area' => ['nullable', 'string'], 'city' => ['nullable', 'string'], 'note' => ['nullable', 'string'], 'status' => ['nullable', 'in:active,blocked']]);
        $c->update($data);
        return $this->ok($c->refresh(), 'Customer updated');
    }
    public function deleteCustomer(Request $request, int $id)
    {
        Customer::where('business_id', $this->business($request)->id)->findOrFail($id)->delete();
        return $this->ok(null, 'Customer deleted');
    }
    public function customerOrders(Request $request, int $id)
    {
        $c = Customer::where('business_id', $this->business($request)->id)->findOrFail($id);
        return $this->ok($c->orders()->with('items')->latest()->paginate(20), 'Customer order history');
    }

    public function templates(Request $request)
    {
        $bid = $this->business($request)->id;
        return $this->ok(MessageTemplate::whereNull('business_id')->orWhere('business_id', $bid)->paginate(50), 'Message templates');
    }
    public function createTemplate(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string'], 'type' => ['required', 'in:order_confirmation,payment_reminder,delivery_update,custom'], 'language' => ['nullable', 'string'], 'body' => ['required', 'string'], 'variables_json' => ['nullable', 'array'], 'status' => ['nullable', 'in:active,inactive']]);
        $data['business_id'] = $this->business($request)->id;
        $data['language'] = $data['language'] ?? 'bn';
        $data['status'] = $data['status'] ?? 'active';
        return $this->ok(MessageTemplate::create($data), 'Template created', 201);
    }

    private function guardOwnerStaffChange(Request $request, ?string $roleCode): void
    {
        if ($roleCode !== 'owner' || $request->user()?->is_super_admin) return;
        $currentRole = BusinessUser::where('business_id', $this->business($request)->id)->where('user_id', $request->user()->id)->where('status', 'active')->with('role')->first()?->role?->code;
        abort_unless($currentRole === 'owner', 403, 'Owner role changes require owner access');
    }
    private function uniqueSlug(string $model, int $businessId, string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 2;
        while ($model::where('business_id', $businessId)->where('slug', $slug)->when($ignore, fn($q) => $q->where('id', '!=', $ignore))->exists()) $slug = $base . '-' . $i++;
        return $slug;
    }
}

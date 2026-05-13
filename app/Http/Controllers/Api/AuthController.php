<?php

namespace App\Http\Controllers\Api;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends ApiController
{
    private function userWithAccess(User $user): array
    {
        $payload = $user->load('businesses')->toArray();
        $payload['avatar_url'] = $user->avatar ? Storage::disk('public')->url($user->avatar) : null;

        if ($user->is_super_admin) {
            $payload['role'] = 'super_admin';
            $payload['permissions'] = ['super_admin'];
            return $payload;
        }

        $membership = BusinessUser::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('role.permissions')
            ->first();

        $payload['role'] = $membership?->role?->code;
        $payload['permissions'] = $membership?->role?->permissions?->pluck('code')->values()->all() ?? [];

        return $payload;
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'business_name' => ['required', 'string', 'max:180'],
            'business_phone' => ['nullable', 'string'],
            'business_email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'facebook_page_url' => ['nullable', 'url'],
            'whatsapp_number' => ['nullable', 'string'],
            'instagram_url' => ['nullable', 'url'],
        ]);
        if (empty($data['email']) && empty($data['phone'])) return $this->fail('Email or phone required', 422);
        return DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null, 'password' => Hash::make($data['password']), 'status' => 'active']);
            $slug = Str::slug($data['business_name']) ?: 'business';
            $base = $slug;
            $i = 2;
            while (Business::where('slug', $slug)->exists()) $slug = $base . '-' . $i++;
            $business = Business::create([
                'owner_id' => $user->id,
                'name' => $data['business_name'],
                'slug' => $slug,
                'phone' => $data['business_phone'] ?? ($data['phone'] ?? null),
                'email' => $data['business_email'] ?? ($data['email'] ?? null),
                'address' => $data['address'] ?? null,
                'facebook_page_url' => $data['facebook_page_url'] ?? null,
                'whatsapp_number' => $data['whatsapp_number'] ?? null,
                'instagram_url' => $data['instagram_url'] ?? null,
                'currency' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'invoice_prefix' => 'SB',
                'status' => 'active'
            ]);
            $role = Role::where('code', 'owner')->firstOrFail();
            $business->users()->attach($user->id, ['role_id' => $role->id, 'status' => 'active', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $plan = Plan::where('slug', 'free')->firstOrFail();
            $subscription = Subscription::create(['business_id' => $business->id, 'plan_id' => $plan->id, 'status' => 'trial', 'starts_at' => now(), 'trial_ends_at' => now()->addDays(14), 'billing_cycle' => 'monthly']);
            $token = $user->createToken('shopbot-web')->plainTextToken;
            $user = $this->userWithAccess($user);
            return $this->ok(compact('user', 'business', 'subscription', 'token'), 'Registration successful', 201);
        });
    }

    public function login(Request $request)
    {
        $data = $request->validate(['login' => ['required', 'string'], 'password' => ['required', 'string'], 'device_name' => ['nullable', 'string']]);
        $user = User::where('email', $data['login'])->orWhere('phone', $data['login'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) return $this->fail('Invalid credentials', 422);
        if ($user->status !== 'active') return $this->fail('Account inactive', 403);
        $user->update(['last_login_at' => now()]);
        return $this->ok(['user' => $this->userWithAccess($user), 'token' => $user->createToken($data['device_name'] ?? 'web')->plainTextToken], 'Login successful');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return $this->ok(null, 'Logged out');
    }

    public function me(Request $request)
    {
        return $this->ok($this->userWithAccess($request->user()), 'Authenticated user');
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->id)],
        ]);

        if (empty($data['email']) && empty($data['phone'])) {
            return $this->fail('Email or phone required', 422);
        }

        $user->update($data);

        return $this->ok($this->userWithAccess($user->fresh()), 'Profile updated');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        if (!Hash::check($data['current_password'], $user->password)) {
            return $this->fail('Current password is incorrect.', 422, [
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        return $this->ok(null, 'Password changed');
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => ['required', 'image', 'max:2048']]);

        $user = $request->user();
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store("users/{$user->id}/avatar", 'public');
        $user->update(['avatar' => $path]);

        return $this->ok($this->userWithAccess($user->fresh()), 'Profile picture updated');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($request->only('email'));
        return $status === Password::RESET_LINK_SENT ? $this->ok(null, __($status)) : $this->fail(__($status), 422);
    }
    public function resetPassword(Request $request)
    {
        $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'min:8', 'confirmed']]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), fn(User $user, string $password) => $user->forceFill(['password' => Hash::make($password)])->save());
        return $status === Password::PASSWORD_RESET ? $this->ok(null, __($status)) : $this->fail(__($status), 422);
    }
}

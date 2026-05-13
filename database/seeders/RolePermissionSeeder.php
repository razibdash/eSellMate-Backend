<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['View Dashboard','view_dashboard','dashboard'],
            ['Manage Products','manage_products','products'],
            ['Manage Customers','manage_customers','customers'],
            ['Create Orders','create_orders','orders'],
            ['Edit Orders','edit_orders','orders'],
            ['Delete/Cancel Orders','delete_orders','orders'],
            ['Manage Payments','manage_payments','payments'],
            ['View Reports','view_reports','reports'],
            ['Manage Staff','manage_staff','staff'],
            ['Manage Settings','manage_settings','settings'],
            ['Use AI Tools','use_ai_tools','ai'],
        ];
        foreach ($permissions as [$name,$code,$module]) Permission::updateOrCreate(['code'=>$code], ['name'=>$name,'module'=>$module]);

        $matrix = [
            'owner' => ['View Dashboard','all'],
            'manager' => ['view_dashboard','manage_products','manage_customers','create_orders','edit_orders','delete_orders','manage_payments','view_reports','manage_staff','use_ai_tools'],
            'staff' => ['view_dashboard','manage_customers','create_orders','edit_orders','manage_payments','use_ai_tools'],
            'delivery_staff' => ['view_dashboard','edit_orders'],
            'viewer' => ['view_dashboard','view_reports'],
        ];

        $roles = [
            ['Owner','owner'], ['Manager','manager'], ['Staff','staff'], ['Delivery Staff','delivery_staff'], ['Viewer','viewer'],
        ];
        foreach ($roles as [$name,$code]) {
            $role = Role::updateOrCreate(['code'=>$code], ['name'=>$name,'description'=>$name.' role','is_system'=>true]);
            $codes = $matrix[$code];
            $ids = in_array('all', $codes, true) ? Permission::pluck('id')->all() : Permission::whereIn('code',$codes)->pluck('id')->all();
            $role->permissions()->sync($ids);
        }
    }
}

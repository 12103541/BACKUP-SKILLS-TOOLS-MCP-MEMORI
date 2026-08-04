<?php
/**
 * Run: cd 'C:\laragon\www\PT.EXFERIA PUTRA INOVASI' && php artisan tinker --execute='include "scripts/test-all-roles.php";'
 * OR paste sections into tinker manually.
 *
 * Layer 1: Panel Access
 * Layer 2: hasPermission per role
 * Layer 3: hasModuleAccess matrix
 * Layer 4: User overrides (user_permissions)
 */

use App\Models\User;
use App\Models\Permission;

// ── Layer 1: Panel Access ──
echo "=== LAYER 1: PANEL ACCESS ===\n";
foreach (User::all() as $u) {
    $panel = app('filament')->getPanel('admin');
    $can = $u->canAccessPanel($panel) ? "PASS" : "FAIL";
    echo "$can | ID:{$u->id} | {$u->name} | Role:{$u->role}\n";
}

// ── Layer 2: Permission Count per Role ──
echo "\n=== LAYER 2: PERMISSION COUNTS ===\n";
$allPerms = Permission::all();
$roles = ['R00','R01','R02','R03','R04','R05','R06','R07'];
$roleNames = [
    'R00'=>'SuperAdmin','R01'=>'AdminProyek','R02'=>'Teknisi',
    'R03'=>'Supervisor','R04'=>'Gudang','R05'=>'Keuangan',
    'R06'=>'Manajer','R07'=>'HRD',
];

foreach ($roles as $code) {
    $user = User::where('role', $code)->first();
    if (!$user) { echo "NO USER: $code\n"; continue; }
    $granted = 0;
    $denied = [];
    foreach ($allPerms as $p) {
        if ($user->hasPermission($p->kode)) { $granted++; }
        else { $denied[] = $p->kode; }
    }
    $total = $allPerms->count();
    $label = $roleNames[$code] ?? $code;
    echo "PASS $code ($label): $granted/$total granted";
    if (count($denied) > 0) echo " | denied: " . implode(',', $denied);
    echo "\n";
}

// ── Layer 3: Module Access Matrix ──
echo "\n=== LAYER 3: MODULE ACCESS MATRIX ===\n";
$modules = Permission::distinct()->pluck('modul')->filter()->sort()->toArray();
echo str_pad('MODULE', 22);
foreach ($roles as $r) echo str_pad($roleNames[$r] ?? $r, 14);
echo "\n" . str_repeat('=', 22 + 14 * count($roles)) . "\n";
foreach ($modules as $m) {
    echo str_pad($m, 22);
    foreach ($roles as $r) {
        $user = User::where('role', $r)->first();
        echo str_pad($user && $user->hasModuleAccess($m) ? '✓' : '✗', 14);
    }
    echo "\n";
}

// ── Layer 4: User Overrides ──
echo "\n=== LAYER 4: USER PERMISSION OVERRIDES ===\n";
$overrides = \App\Models\UserPermission::with('permission')->get()->groupBy('user_id');
foreach ($overrides as $userId => $perms) {
    $user = User::find($userId);
    if (!$user) continue;
    echo "User {$user->name} ({$user->role}) overrides:\n";
    foreach ($perms as $up) {
        $action = $up->granted ? 'GRANT' : 'REVOKE';
        echo "  $action {$up->permission->kode}\n";
    }
}
echo "\n=== TEST COMPLETE ===\n";

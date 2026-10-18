<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar definition. Items are shown only when the route exists and the
 * user holds the permission; the server enforces the same permission on
 * the route itself, so hiding a link is never the only protection.
 */
final class Navigation
{
    public static function groups(): array
    {
        return [
            'nav.group_overview' => [
                ['route' => 'dashboard', 'label' => 'nav.dashboard', 'icon' => 'home', 'can' => 'dashboard.view', 'active' => 'dashboard'],
                ['route' => 'approvals.index', 'label' => 'nav.approvals', 'icon' => 'check-circle', 'can' => null, 'active' => 'approvals.*'],
                ['route' => 'notifications.index', 'label' => 'nav.notifications', 'icon' => 'bell', 'can' => null, 'active' => 'notifications.*'],
            ],
            'nav.group_work' => [
                ['route' => 'projects.index', 'label' => 'nav.projects', 'icon' => 'briefcase', 'can' => 'projects.view', 'active' => 'projects.*'],
                ['route' => 'tasks.index', 'label' => 'nav.tasks', 'icon' => 'list', 'can' => null, 'active' => 'tasks.*'],
                ['route' => 'meetings.index', 'label' => 'nav.meetings', 'icon' => 'calendar', 'can' => 'meetings.view', 'active' => 'meetings.*'],
            ],
            'nav.group_records' => [
                ['route' => 'documents.index', 'label' => 'nav.documents', 'icon' => 'file', 'can' => 'documents.view', 'active' => 'documents.*'],
                ['route' => 'correspondence.index', 'label' => 'nav.correspondence', 'icon' => 'mail', 'can' => 'correspondence.view', 'active' => 'correspondence.*'],
            ],
            'nav.group_organisation' => [
                ['route' => 'staff.index', 'label' => 'nav.staff', 'icon' => 'users', 'can' => 'staff.view', 'active' => 'staff.*'],
                ['route' => 'departments.index', 'label' => 'nav.departments', 'icon' => 'building', 'can' => 'departments.view', 'active' => 'departments.*'],
            ],
            'nav.group_resources' => [
                ['route' => 'assets.index', 'label' => 'nav.assets', 'icon' => 'box', 'can' => 'assets.view', 'active' => 'assets.*'],
                ['route' => 'suppliers.index', 'label' => 'nav.suppliers', 'icon' => 'truck', 'can' => 'suppliers.view', 'active' => ['suppliers.*', 'contracts.*']],
                ['route' => 'purchase-requests.index', 'label' => 'nav.procurement', 'icon' => 'cart', 'can' => 'procurement.view', 'active' => ['purchase-requests.*', 'purchase-orders.*']],
                ['route' => 'budgets.index', 'label' => 'nav.budgets', 'icon' => 'wallet', 'can' => 'budgets.view', 'active' => 'budgets.*'],
                ['route' => 'expenses.index', 'label' => 'nav.expenses', 'icon' => 'receipt', 'can' => 'expenses.view', 'active' => 'expenses.*'],
            ],
            'nav.group_insight' => [
                ['route' => 'reports.index', 'label' => 'nav.reports', 'icon' => 'chart', 'can' => 'reports.view', 'active' => 'reports.*'],
            ],
            'nav.group_admin' => [
                ['route' => 'admin.users.index', 'label' => 'nav.users', 'icon' => 'user-shield', 'can' => 'users.view', 'active' => 'admin.users.*'],
                ['route' => 'admin.roles.index', 'label' => 'nav.roles', 'icon' => 'key', 'can' => 'roles.manage', 'active' => 'admin.roles.*'],
                ['route' => 'admin.permission-reviews.index', 'label' => 'nav.permission_reviews', 'icon' => 'clipboard', 'can' => 'permissions.review', 'active' => 'admin.permission-reviews.*'],
                ['route' => 'admin.audit.index', 'label' => 'nav.audit', 'icon' => 'shield', 'can' => 'audit.view', 'active' => 'admin.audit.*'],
                ['route' => 'admin.settings.edit', 'label' => 'nav.settings', 'icon' => 'cog', 'can' => 'settings.manage', 'active' => ['admin.settings.*', 'admin.lookups.*']],
                ['route' => 'admin.system.index', 'label' => 'nav.system', 'icon' => 'server', 'can' => 'system.health', 'active' => 'admin.system.*'],
                ['route' => 'admin.backups.index', 'label' => 'nav.backups', 'icon' => 'database', 'can' => 'backups.manage', 'active' => 'admin.backups.*'],
            ],
        ];
    }

    /** Groups filtered for the given user (empty groups removed). */
    public static function for(User $user): array
    {
        $result = [];
        foreach (self::groups() as $group => $items) {
            $visible = array_values(array_filter($items, fn ($item) => Route::has($item['route']) && ($item['can'] === null || $user->can($item['can']))));
            if ($visible !== []) {
                $result[$group] = $visible;
            }
        }

        return $result;
    }
}

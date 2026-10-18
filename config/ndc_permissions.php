<?php

/*
 * Single source of truth for permissions and the default role matrix.
 * Seeded by Database\Seeders\RolesAndPermissionsSeeder.
 * Roles can be adjusted later by a Super Administrator in the UI;
 * re-running the seeder only adds missing permissions/roles, it never
 * removes permissions an administrator granted.
 */

return [

    'permissions' => [
        'dashboard' => [
            'dashboard.view' => 'View the executive dashboard',
        ],
        'administration' => [
            'users.view' => 'View user accounts',
            'users.manage' => 'Create, edit, activate and suspend user accounts',
            'roles.manage' => 'Create and edit roles and their permissions',
            'permissions.review' => 'Record periodic permission reviews',
            'settings.manage' => 'Change organisation and system settings',
            'audit.view' => 'View audit and login activity logs',
            'system.health' => 'View application health and error logs',
            'backups.manage' => 'View and record backup status',
        ],
        'organisation' => [
            'departments.view' => 'View departments',
            'departments.manage' => 'Create and edit departments',
            'staff.view' => 'View the staff directory',
            'staff.view_sensitive' => 'View full staff records (employment details, notes)',
            'staff.manage' => 'Register and edit staff records',
        ],
        'projects' => [
            'projects.view' => 'View projects',
            'projects.create' => 'Create projects',
            'projects.update' => 'Edit projects the user manages',
            'projects.manage' => 'Edit and archive any project',
        ],
        'tasks' => [
            'tasks.view_all' => 'View all tasks (not only own / own projects)',
            'tasks.create' => 'Create and assign tasks',
            'tasks.manage' => 'Edit and reassign any task',
            'tasks.approve' => 'Approve or reject task completion',
        ],
        'documents' => [
            'documents.view' => 'View public and internal documents',
            'documents.create' => 'Register documents and upload versions',
            'documents.manage' => 'Edit, archive and manage access of any document',
            'documents.approve' => 'Approve or reject documents',
            'documents.view_confidential' => 'View confidential documents of any department',
            'documents.view_restricted' => 'View restricted documents',
        ],
        'correspondence' => [
            'correspondence.view' => 'View the correspondence registers',
            'correspondence.manage' => 'Register, assign and forward correspondence',
            'correspondence.view_confidential' => 'View confidential correspondence',
        ],
        'meetings' => [
            'meetings.view' => 'View meetings and calendar',
            'meetings.manage' => 'Schedule meetings and record minutes',
        ],
        'assets' => [
            'assets.view' => 'View asset inventory',
            'assets.manage' => 'Register, assign, transfer, maintain and retire assets',
        ],
        'procurement' => [
            'suppliers.view' => 'View suppliers and contracts',
            'suppliers.manage' => 'Register and edit suppliers and contracts',
            'procurement.view' => 'View purchase requests and orders',
            'procurement.request' => 'Create and submit purchase requests',
            'procurement.approve' => 'Approve or reject purchase requests',
            'procurement.order' => 'Record quotations and purchase orders',
            'procurement.receive' => 'Record goods received',
        ],
        'finance' => [
            'budgets.view' => 'View budgets and allocations',
            'budgets.manage' => 'Create and edit budgets and allocations',
            'budgets.approve' => 'Approve budgets',
            'expenses.view' => 'View recorded expenses',
            'expenses.record' => 'Record expenses',
            'expenses.approve' => 'Approve or reject expenses',
            'expenses.settle' => 'Mark expenses as paid / reconciled (record only, no payment)',
        ],
        'reports' => [
            'reports.view' => 'View operational reports',
            'reports.financial' => 'View budget, expense and procurement reports',
            'reports.export' => 'Export reports to CSV / PDF',
        ],
    ],

    /*
     * Default roles. '*' = every permission (Super Administrator only).
     */
    'roles' => [
        'super-admin' => [
            'name' => 'Super Administrator',
            'description' => 'Full access to every function. Keep to a minimum number of accounts.',
            'permissions' => ['*'],
        ],
        'system-admin' => [
            'name' => 'System Administrator',
            'description' => 'Manages users, settings, backups and system health. No financial approval rights.',
            'permissions' => [
                'dashboard.view', 'users.view', 'users.manage', 'permissions.review', 'settings.manage',
                'audit.view', 'system.health', 'backups.manage', 'departments.view', 'departments.manage',
                'staff.view', 'staff.view_sensitive', 'staff.manage', 'projects.view', 'documents.view',
                'meetings.view', 'assets.view', 'reports.view',
            ],
        ],
        'director' => [
            'name' => 'Director / Management',
            'description' => 'Organisation-wide visibility and approvals.',
            'permissions' => [
                'dashboard.view', 'departments.view', 'staff.view', 'staff.view_sensitive',
                'projects.view', 'projects.create', 'projects.update', 'projects.manage',
                'tasks.view_all', 'tasks.create', 'tasks.manage', 'tasks.approve',
                'documents.view', 'documents.create', 'documents.approve', 'documents.view_confidential', 'documents.view_restricted',
                'correspondence.view', 'correspondence.manage', 'correspondence.view_confidential',
                'meetings.view', 'meetings.manage', 'assets.view', 'suppliers.view',
                'procurement.view', 'procurement.approve', 'budgets.view', 'budgets.approve',
                'expenses.view', 'expenses.approve', 'reports.view', 'reports.financial', 'reports.export', 'audit.view',
            ],
        ],
        'project-manager' => [
            'name' => 'Project Manager',
            'description' => 'Creates and runs projects, assigns and approves project tasks.',
            'permissions' => [
                'dashboard.view', 'departments.view', 'staff.view', 'projects.view', 'projects.create', 'projects.update',
                'tasks.create', 'tasks.approve', 'documents.view', 'documents.create',
                'correspondence.view', 'meetings.view', 'meetings.manage', 'assets.view', 'suppliers.view',
                'procurement.view', 'procurement.request', 'budgets.view', 'expenses.view', 'expenses.record',
                'reports.view', 'reports.export',
            ],
        ],
        'department-head' => [
            'name' => 'Department Head',
            'description' => 'Oversees a department: staff, tasks, documents and requests.',
            'permissions' => [
                'dashboard.view', 'departments.view', 'staff.view', 'staff.view_sensitive', 'projects.view',
                'tasks.view_all', 'tasks.create', 'tasks.approve', 'documents.view', 'documents.create', 'documents.approve',
                'correspondence.view', 'correspondence.manage', 'meetings.view', 'meetings.manage', 'assets.view',
                'suppliers.view', 'procurement.view', 'procurement.request', 'procurement.approve',
                'budgets.view', 'expenses.view', 'reports.view', 'reports.export',
            ],
        ],
        'project-officer' => [
            'name' => 'Project Officer',
            'description' => 'Works on project tasks and records project information.',
            'permissions' => [
                'dashboard.view', 'departments.view', 'staff.view', 'projects.view', 'tasks.create',
                'documents.view', 'documents.create', 'meetings.view', 'assets.view', 'procurement.view',
                'procurement.request', 'reports.view',
            ],
        ],
        'document-officer' => [
            'name' => 'Document Officer',
            'description' => 'Maintains the document repository and correspondence registers.',
            'permissions' => [
                'dashboard.view', 'departments.view', 'staff.view', 'projects.view', 'documents.view', 'documents.create',
                'documents.manage', 'correspondence.view', 'correspondence.manage', 'meetings.view', 'meetings.manage',
                'reports.view', 'reports.export',
            ],
        ],
        'finance-officer' => [
            'name' => 'Procurement / Finance Officer',
            'description' => 'Suppliers, purchase orders, goods receipt, budgets and expense recording. Cannot approve.',
            'permissions' => [
                'dashboard.view', 'departments.view', 'staff.view', 'projects.view', 'documents.view', 'documents.create',
                'assets.view', 'assets.manage', 'suppliers.view', 'suppliers.manage', 'procurement.view', 'procurement.request',
                'procurement.order', 'procurement.receive', 'budgets.view', 'budgets.manage', 'expenses.view',
                'expenses.record', 'expenses.settle', 'reports.view', 'reports.financial', 'reports.export',
            ],
        ],
        'staff' => [
            'name' => 'General Staff',
            'description' => 'Own tasks, directory, internal documents, meetings.',
            'permissions' => [
                'dashboard.view', 'departments.view', 'staff.view', 'projects.view', 'documents.view',
                'meetings.view', 'procurement.request', 'procurement.view',
            ],
        ],
        'auditor' => [
            'name' => 'Read-Only Auditor',
            'description' => 'Read-only access to records, reports and audit logs. Cannot change data.',
            'permissions' => [
                'dashboard.view', 'users.view', 'audit.view', 'departments.view', 'staff.view', 'staff.view_sensitive',
                'projects.view', 'tasks.view_all', 'documents.view', 'documents.view_confidential', 'correspondence.view',
                'correspondence.view_confidential', 'meetings.view', 'assets.view', 'suppliers.view', 'procurement.view',
                'budgets.view', 'expenses.view', 'reports.view', 'reports.financial', 'reports.export',
            ],
        ],
    ],
];

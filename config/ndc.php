<?php

/*
|--------------------------------------------------------------------------
| NDC Somalia Management System configuration
|--------------------------------------------------------------------------
|
| The permission catalogue, default role matrix and navigation live here so
| that every module reads them from one place. Roles and permissions are
| seeded from this file but remain editable in Administration > Roles, so the
| final matrix can follow NDC's approved organisational structure.
|
*/

return [

    'name' => env('APP_NAME', 'NDC Somalia Management System'),

    'organisation' => 'National Data Center Somalia',

    /*
    | Supported interface languages. "dir" controls the HTML text direction so
    | a right-to-left language can be added later without layout changes.
    */
    'locales' => [
        'en' => ['name' => 'English', 'native' => 'English', 'dir' => 'ltr'],
        'so' => ['name' => 'Somali', 'native' => 'Soomaali', 'dir' => 'ltr'],
    ],

    /*
    | Permission catalogue grouped by module. Keys are permission names.
    */
    'permissions' => [
        'dashboard' => [
            'dashboard.view' => 'View dashboard',
        ],
        'projects' => [
            'projects.view' => 'View projects',
            'projects.create' => 'Create projects',
            'projects.update' => 'Edit projects, milestones, risks and stakeholders',
            'projects.archive' => 'Archive and delete projects',
        ],
        'tasks' => [
            'tasks.view' => 'View own and department tasks',
            'tasks.view-all' => 'View all tasks',
            'tasks.create' => 'Create and assign tasks',
            'tasks.update' => 'Edit tasks',
            'tasks.delete' => 'Delete tasks',
            'tasks.review' => 'Review, approve and reject submitted tasks',
        ],
        'staff' => [
            'staff.view' => 'View staff directory',
            'staff.create' => 'Register staff',
            'staff.update' => 'Edit staff profiles',
            'staff.delete' => 'Delete staff records',
            'staff.view-sensitive' => 'View sensitive personal information',
        ],
        'documents' => [
            'documents.view' => 'View documents',
            'documents.create' => 'Upload and register documents',
            'documents.update' => 'Edit documents and upload new versions',
            'documents.delete' => 'Archive and delete documents',
            'documents.approve' => 'Approve and publish documents',
            'documents.view-confidential' => 'Access confidential documents',
        ],
        'correspondence' => [
            'correspondence.view' => 'View correspondence',
            'correspondence.create' => 'Register correspondence',
            'correspondence.update' => 'Edit, assign and close correspondence',
            'correspondence.delete' => 'Delete correspondence',
            'correspondence.view-confidential' => 'Access confidential correspondence',
        ],
        'meetings' => [
            'meetings.view' => 'View meetings and calendar',
            'meetings.create' => 'Schedule meetings',
            'meetings.update' => 'Edit meetings, minutes and action points',
            'meetings.delete' => 'Delete meetings',
        ],
        'assets' => [
            'assets.view' => 'View assets',
            'assets.create' => 'Register assets',
            'assets.update' => 'Edit assets, assignments and maintenance',
            'assets.delete' => 'Delete assets',
            'assets.approve-disposal' => 'Approve asset disposal and retirement',
        ],
        'procurement' => [
            'procurement.view' => 'View procurement records',
            'procurement.create' => 'Create purchase requests',
            'procurement.update' => 'Manage quotations, orders and deliveries',
            'procurement.delete' => 'Delete procurement records',
            'procurement.approve' => 'Approve purchase requests',
            'suppliers.manage' => 'Manage suppliers and contractors',
        ],
        'budgets' => [
            'budgets.view' => 'View budgets and expenses',
            'budgets.create' => 'Create budgets',
            'budgets.update' => 'Edit budgets and allocations',
            'budgets.delete' => 'Delete budgets',
            'budgets.approve' => 'Approve budgets',
            'expenses.create' => 'Record expenses',
            'expenses.approve' => 'Approve or reject expenses',
        ],
        'reports' => [
            'reports.view' => 'View reports',
            'reports.export' => 'Export reports (PDF/CSV)',
        ],
        'administration' => [
            'users.manage' => 'Manage user accounts',
            'roles.manage' => 'Manage roles and permissions',
            'departments.manage' => 'Manage departments',
            'settings.manage' => 'Manage system settings and reference data',
            'audit.view' => 'View audit and activity logs',
            'system.manage' => 'View system health and manage backups',
            'api.access' => 'Use the integration API',
        ],
    ],

    /*
    | Default role matrix. "Super Administrator" bypasses all checks via a
    | Gate::before hook and therefore needs no explicit permissions.
    | A trailing ".*" grants every permission in that module group.
    */
    'super_admin_role' => 'Super Administrator',

    'roles' => [
        'Super Administrator' => [],
        'System Administrator' => [
            'dashboard.*', 'administration.*',
            'projects.view', 'tasks.view', 'staff.view', 'documents.view', 'correspondence.view',
            'meetings.view', 'assets.view', 'procurement.view', 'budgets.view', 'reports.view',
        ],
        'Director or Management' => [
            'dashboard.*', 'projects.*', 'tasks.*', 'staff.view', 'staff.view-sensitive', 'documents.*',
            'correspondence.*', 'meetings.*', 'assets.view', 'assets.approve-disposal',
            'procurement.view', 'procurement.approve', 'budgets.view', 'budgets.approve', 'expenses.approve',
            'reports.*', 'audit.view',
        ],
        'Project Manager' => [
            'dashboard.*', 'projects.view', 'projects.create', 'projects.update', 'tasks.view', 'tasks.view-all',
            'tasks.create', 'tasks.update', 'tasks.review', 'staff.view', 'documents.view', 'documents.create',
            'documents.update', 'correspondence.view', 'meetings.*', 'assets.view', 'procurement.view',
            'procurement.create', 'budgets.view', 'expenses.create', 'reports.*',
        ],
        'Department Head' => [
            'dashboard.*', 'projects.view', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.review',
            'staff.view', 'documents.view', 'documents.create', 'documents.update', 'documents.approve',
            'correspondence.view', 'correspondence.create', 'correspondence.update', 'meetings.*',
            'assets.view', 'procurement.view', 'procurement.create', 'budgets.view', 'reports.*',
        ],
        'Project Officer' => [
            'dashboard.*', 'projects.view', 'projects.update', 'tasks.view', 'tasks.create', 'tasks.update',
            'staff.view', 'documents.view', 'documents.create', 'documents.update', 'correspondence.view',
            'meetings.view', 'meetings.create', 'meetings.update', 'assets.view', 'procurement.view',
            'procurement.create', 'budgets.view', 'reports.view',
        ],
        'Document Officer' => [
            'dashboard.*', 'projects.view', 'tasks.view', 'staff.view', 'documents.view', 'documents.create',
            'documents.update', 'documents.delete', 'documents.view-confidential', 'correspondence.*',
            'meetings.view', 'reports.view', 'reports.export',
        ],
        'Finance or Procurement Officer' => [
            'dashboard.*', 'projects.view', 'tasks.view', 'staff.view', 'documents.view', 'documents.create',
            'assets.view', 'assets.create', 'assets.update', 'procurement.view', 'procurement.create',
            'procurement.update', 'suppliers.manage', 'budgets.view', 'budgets.create', 'budgets.update',
            'expenses.create', 'meetings.view', 'reports.*',
        ],
        'General Staff' => [
            'dashboard.view', 'tasks.view', 'staff.view', 'documents.view', 'meetings.view', 'assets.view',
        ],
        'Read-Only Auditor' => [
            'dashboard.view', 'projects.view', 'tasks.view', 'tasks.view-all', 'staff.view', 'documents.view',
            'documents.view-confidential', 'correspondence.view', 'correspondence.view-confidential',
            'meetings.view', 'assets.view', 'procurement.view', 'budgets.view', 'reports.*', 'audit.view',
        ],
    ],

    /*
    | Sidebar navigation. Items whose route is not registered or whose
    | permission the user lacks are hidden automatically.
    */
    'navigation' => [
        ['section' => null, 'items' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'can' => 'dashboard.view', 'active' => 'dashboard'],
        ]],
        ['section' => 'Work', 'items' => [
            ['label' => 'Projects', 'route' => 'projects.index', 'icon' => 'folder', 'can' => 'projects.view', 'active' => 'projects.*'],
            ['label' => 'Tasks', 'route' => 'tasks.index', 'icon' => 'check', 'can' => 'tasks.view', 'active' => 'tasks.*'],
            ['label' => 'Meetings & Calendar', 'route' => 'meetings.index', 'icon' => 'calendar', 'can' => 'meetings.view', 'active' => 'meetings.*'],
        ]],
        ['section' => 'Records', 'items' => [
            ['label' => 'Documents', 'route' => 'documents.index', 'icon' => 'file', 'can' => 'documents.view', 'active' => 'documents.*'],
            ['label' => 'Correspondence', 'route' => 'correspondence.index', 'icon' => 'mail', 'can' => 'correspondence.view', 'active' => 'correspondence.*'],
            ['label' => 'Staff', 'route' => 'staff.index', 'icon' => 'users', 'can' => 'staff.view', 'active' => 'staff.*'],
        ]],
        ['section' => 'Resources', 'items' => [
            ['label' => 'Assets', 'route' => 'assets.index', 'icon' => 'box', 'can' => 'assets.view', 'active' => 'assets.*'],
            ['label' => 'Procurement', 'route' => 'procurement.index', 'icon' => 'cart', 'can' => 'procurement.view', 'active' => ['procurement.*', 'purchase-orders.*']],
            ['label' => 'Suppliers', 'route' => 'suppliers.index', 'icon' => 'truck', 'can' => 'procurement.view', 'active' => 'suppliers.*'],
            ['label' => 'Budgets & Expenses', 'route' => 'budgets.index', 'icon' => 'wallet', 'can' => 'budgets.view', 'active' => ['budgets.*', 'expenses.*']],
        ]],
        ['section' => 'Insight', 'items' => [
            ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'chart', 'can' => 'reports.view', 'active' => 'reports.*'],
            ['label' => 'Approvals', 'route' => 'approvals.index', 'icon' => 'stamp', 'can' => null, 'active' => 'approvals.*'],
        ]],
        ['section' => 'Administration', 'items' => [
            ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'user-cog', 'can' => 'users.manage', 'active' => 'admin.users.*'],
            ['label' => 'Roles & Permissions', 'route' => 'admin.roles.index', 'icon' => 'shield', 'can' => 'roles.manage', 'active' => 'admin.roles.*'],
            ['label' => 'Departments', 'route' => 'admin.departments.index', 'icon' => 'building', 'can' => 'departments.manage', 'active' => 'admin.departments.*'],
            ['label' => 'Reference Data', 'route' => 'admin.categories.index', 'icon' => 'tags', 'can' => 'settings.manage', 'active' => 'admin.categories.*'],
            ['label' => 'Settings', 'route' => 'admin.settings.edit', 'icon' => 'settings', 'can' => 'settings.manage', 'active' => 'admin.settings.*'],
            ['label' => 'Audit Log', 'route' => 'admin.audit.index', 'icon' => 'history', 'can' => 'audit.view', 'active' => 'admin.audit.*'],
            ['label' => 'System Health', 'route' => 'admin.system.index', 'icon' => 'activity', 'can' => 'system.manage', 'active' => ['admin.system.*', 'admin.backups.*']],
        ]],
    ],

    /*
    | Reference-data category types managed under Administration.
    */
    'category_types' => [
        'document' => 'Document types',
        'asset' => 'Asset categories',
        'expense' => 'Expense categories',
        'supplier' => 'Supplier categories',
        'funding_source' => 'Funding sources',
    ],

    /*
    | Defaults for runtime settings (overridable in Administration > Settings).
    */
    'settings' => [
        'organisation_name' => 'National Data Center Somalia',
        'default_locale' => 'en',
        'date_format' => 'd M Y',
        'datetime_format' => 'd M Y H:i',
        'currency' => 'USD',
        'upload_max_kb' => 20480,
        'upload_allowed_extensions' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,gif,webp',
        'mail_notifications_enabled' => false,
        'reminder_days_before_due' => 3,
        'document_expiry_reminder_days' => 30,
        'warranty_reminder_days' => 30,
        'audit_retention_days' => 1825,
        'notification_retention_days' => 365,
        'backup_retention_days' => 30,
        'require_two_factor_for_admins' => false,
        'password_min_length' => 10,
        'session_timeout_minutes' => 120,
    ],

    'backup' => [
        'disk' => env('BACKUP_DISK', 'backups'),
        'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
        'mysql_path' => env('MYSQL_PATH', 'mysql'),
    ],

];

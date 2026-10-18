<?php

/*
 * NDC-SMS application defaults. Values marked "setting" can be changed by
 * an administrator at runtime (stored in the `settings` table); the values
 * here are only the defaults used until a setting is saved.
 */
return [

    'version' => '1.0.0',

    'settings_defaults' => [
        // Organisation
        'org.name' => 'National Data Center Somalia',
        'org.short_name' => 'NDC',
        'org.address' => '',
        'org.email' => '',
        'org.phone' => '',

        // Localisation and formats
        'locale.default' => 'en',
        'format.date' => 'd/m/Y',
        'format.datetime' => 'd/m/Y H:i',
        'format.currency' => 'USD',
        'format.currency_symbol' => '$',

        // Reference numbering: {org}-{prefix}-{year}-{number}
        'numbering.org_prefix' => 'NDC',
        'numbering.padding' => 4,
        'numbering.prefix.project' => 'PRJ',
        'numbering.prefix.task' => 'TSK',
        'numbering.prefix.document' => 'DOC',
        'numbering.prefix.correspondence_in' => 'IN',
        'numbering.prefix.correspondence_out' => 'OUT',
        'numbering.prefix.meeting' => 'MTG',
        'numbering.prefix.asset' => 'AST',
        'numbering.prefix.supplier' => 'SUP',
        'numbering.prefix.contract' => 'CTR',
        'numbering.prefix.purchase_request' => 'PR',
        'numbering.prefix.purchase_order' => 'PO',
        'numbering.prefix.goods_receipt' => 'GRN',
        'numbering.prefix.budget' => 'BGT',
        'numbering.prefix.expense' => 'EXP',

        // File upload policy
        'uploads.max_kb' => 20480,
        'uploads.allowed_extensions' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,txt,csv,jpg,jpeg,png',

        // Notifications
        'notifications.email_enabled' => false,
        'notifications.deadline_days_before' => 2,
        'notifications.meeting_hours_before' => 24,
        'notifications.expiry_days_before' => 30,

        // Retention (years). Used by the archive report; nothing is deleted automatically.
        'retention.audit_logs_years' => 7,
        'retention.closed_correspondence_years' => 10,

        // Security
        'security.session_timeout_minutes' => 60,
        'security.permission_review_days' => 90,
    ],

    /*
     * Extension => accepted MIME types (detected from file content with finfo).
     * Extensions not listed here can never be uploaded, whatever the setting says.
     */
    'upload_mime_map' => [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ],

    // Formats that can be shown inline in the browser (everything else is forced to download)
    'inline_preview_mimes' => ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'],
];

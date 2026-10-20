<?php

/*
|--------------------------------------------------------------------------
| Status vocabularies
|--------------------------------------------------------------------------
|
| Every status column in the system takes its allowed values from here.
| Each value maps to [English label, badge tone]. Labels are translated at
| render time with __(), so Somali strings live in lang/so.json.
| Tones: neutral, info, success, warning, danger, accent.
|
*/

return [

    'user' => [
        'active' => ['Active', 'success'],
        'suspended' => ['Suspended', 'warning'],
        'deactivated' => ['Deactivated', 'neutral'],
    ],

    'project' => [
        'planned' => ['Planned', 'neutral'],
        'in_progress' => ['In Progress', 'info'],
        'on_hold' => ['On Hold', 'warning'],
        'completed' => ['Completed', 'success'],
        'cancelled' => ['Cancelled', 'danger'],
    ],

    'priority' => [
        'low' => ['Low', 'neutral'],
        'medium' => ['Medium', 'info'],
        'high' => ['High', 'warning'],
        'urgent' => ['Urgent', 'danger'],
    ],

    'milestone' => [
        'pending' => ['Pending', 'neutral'],
        'in_progress' => ['In Progress', 'info'],
        'completed' => ['Completed', 'success'],
        'delayed' => ['Delayed', 'danger'],
    ],

    'risk' => [
        'open' => ['Open', 'danger'],
        'mitigating' => ['Mitigating', 'warning'],
        'closed' => ['Closed', 'success'],
    ],

    'risk_type' => [
        'risk' => ['Risk', 'warning'],
        'challenge' => ['Challenge', 'info'],
        'delay' => ['Delay', 'danger'],
    ],

    'level' => [
        'low' => ['Low', 'neutral'],
        'medium' => ['Medium', 'warning'],
        'high' => ['High', 'danger'],
    ],

    'stakeholder_type' => [
        'contractor' => ['Contractor', 'info'],
        'consultant' => ['Consultant', 'accent'],
        'stakeholder' => ['Stakeholder', 'neutral'],
        'contact' => ['Contact', 'neutral'],
    ],

    'task' => [
        'pending' => ['Pending', 'neutral'],
        'in_progress' => ['In Progress', 'info'],
        'submitted' => ['Awaiting Review', 'accent'],
        'completed' => ['Completed', 'success'],
        'rejected' => ['Returned', 'danger'],
        'cancelled' => ['Cancelled', 'neutral'],
    ],

    'employment' => [
        'active' => ['Active', 'success'],
        'on_leave' => ['On Leave', 'info'],
        'suspended' => ['Suspended', 'warning'],
        'terminated' => ['Terminated', 'danger'],
        'retired' => ['Retired', 'neutral'],
    ],

    'employment_type' => [
        'permanent' => ['Permanent', 'neutral'],
        'contract' => ['Contract', 'neutral'],
        'temporary' => ['Temporary', 'neutral'],
        'secondment' => ['Secondment', 'neutral'],
        'intern' => ['Intern', 'neutral'],
    ],

    'confidentiality' => [
        'public' => ['Public', 'success'],
        'internal' => ['Internal', 'info'],
        'confidential' => ['Confidential', 'warning'],
        'restricted' => ['Restricted', 'danger'],
    ],

    'document' => [
        'draft' => ['Draft', 'neutral'],
        'pending_review' => ['Pending Review', 'accent'],
        'approved' => ['Approved', 'info'],
        'published' => ['Published', 'success'],
        'rejected' => ['Rejected', 'danger'],
        'archived' => ['Archived', 'neutral'],
    ],

    'correspondence_direction' => [
        'incoming' => ['Incoming', 'info'],
        'outgoing' => ['Outgoing', 'accent'],
    ],

    'correspondence' => [
        'pending' => ['Pending', 'neutral'],
        'received' => ['Received', 'info'],
        'forwarded' => ['Forwarded', 'accent'],
        'responded' => ['Responded', 'success'],
        'sent' => ['Sent', 'success'],
        'closed' => ['Closed', 'neutral'],
    ],

    'correspondence_priority' => [
        'low' => ['Low', 'neutral'],
        'normal' => ['Normal', 'info'],
        'high' => ['High', 'warning'],
        'urgent' => ['Urgent', 'danger'],
    ],

    'meeting_type' => [
        'meeting' => ['Meeting', 'info'],
        'appointment' => ['Appointment', 'accent'],
        'event' => ['Official Event', 'neutral'],
    ],

    'meeting' => [
        'scheduled' => ['Scheduled', 'info'],
        'completed' => ['Completed', 'success'],
        'postponed' => ['Postponed', 'warning'],
        'cancelled' => ['Cancelled', 'danger'],
    ],

    'attendance' => [
        'invited' => ['Invited', 'neutral'],
        'attended' => ['Attended', 'success'],
        'absent' => ['Absent', 'danger'],
        'excused' => ['Excused', 'warning'],
    ],

    'action_item_type' => [
        'decision' => ['Decision', 'accent'],
        'action' => ['Action Point', 'info'],
    ],

    'action_item' => [
        'open' => ['Open', 'neutral'],
        'in_progress' => ['In Progress', 'info'],
        'done' => ['Done', 'success'],
        'cancelled' => ['Cancelled', 'neutral'],
    ],

    'asset' => [
        'available' => ['Available', 'success'],
        'assigned' => ['Assigned', 'info'],
        'under_maintenance' => ['Under Maintenance', 'warning'],
        'damaged' => ['Damaged', 'danger'],
        'retired' => ['Retired', 'neutral'],
        'disposed' => ['Disposed', 'neutral'],
    ],

    'asset_condition' => [
        'new' => ['New', 'success'],
        'good' => ['Good', 'success'],
        'fair' => ['Fair', 'warning'],
        'poor' => ['Poor', 'danger'],
        'damaged' => ['Damaged', 'danger'],
    ],

    'maintenance_type' => [
        'preventive' => ['Preventive', 'info'],
        'corrective' => ['Corrective', 'warning'],
        'warranty' => ['Warranty Repair', 'accent'],
        'inspection' => ['Inspection', 'neutral'],
    ],

    'maintenance' => [
        'scheduled' => ['Scheduled', 'info'],
        'in_progress' => ['In Progress', 'accent'],
        'completed' => ['Completed', 'success'],
        'cancelled' => ['Cancelled', 'neutral'],
    ],

    'disposal_action' => [
        'dispose' => ['Disposal', 'danger'],
        'retire' => ['Retirement', 'neutral'],
    ],

    'disposal' => [
        'pending' => ['Pending Approval', 'accent'],
        'approved' => ['Approved', 'info'],
        'rejected' => ['Rejected', 'danger'],
        'completed' => ['Completed', 'success'],
    ],

    'supplier_type' => [
        'supplier' => ['Supplier', 'info'],
        'contractor' => ['Contractor', 'accent'],
        'consultant' => ['Consultant', 'neutral'],
    ],

    'supplier' => [
        'active' => ['Active', 'success'],
        'inactive' => ['Inactive', 'neutral'],
        'suspended' => ['Suspended', 'danger'],
    ],

    'procurement' => [
        'draft' => ['Draft', 'neutral'],
        'submitted' => ['Pending Approval', 'accent'],
        'approved' => ['Approved', 'info'],
        'rejected' => ['Rejected', 'danger'],
        'ordered' => ['Ordered', 'info'],
        'partially_received' => ['Partially Received', 'warning'],
        'received' => ['Received', 'success'],
        'cancelled' => ['Cancelled', 'neutral'],
    ],

    'purchase_order' => [
        'issued' => ['Issued', 'info'],
        'partially_delivered' => ['Partially Delivered', 'warning'],
        'delivered' => ['Delivered', 'success'],
        'cancelled' => ['Cancelled', 'neutral'],
    ],

    'budget' => [
        'draft' => ['Draft', 'neutral'],
        'submitted' => ['Pending Approval', 'accent'],
        'approved' => ['Approved', 'success'],
        'rejected' => ['Rejected', 'danger'],
        'closed' => ['Closed', 'neutral'],
    ],

    'expense' => [
        'pending' => ['Pending Approval', 'accent'],
        'approved' => ['Approved', 'success'],
        'rejected' => ['Rejected', 'danger'],
    ],

    'approval' => [
        'pending' => ['Pending', 'accent'],
        'approved' => ['Approved', 'success'],
        'rejected' => ['Rejected', 'danger'],
        'cancelled' => ['Cancelled', 'neutral'],
    ],

    'backup' => [
        'running' => ['Running', 'info'],
        'success' => ['Verified', 'success'],
        'unverified' => ['Completed, not verified', 'warning'],
        'failed' => ['Failed', 'danger'],
    ],

];

<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Budget;
use App\Models\Correspondence;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FundingSource;
use App\Models\Location;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Services\FileStorage;
use App\Support\Money;
use App\Support\ReferenceNumber;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DEMONSTRATION DATA ONLY. Every record is prefixed "[DEMO]" and uses
 * example.test addresses. Nothing here describes real NDC staff, projects,
 * budgets, contracts or equipment. Refuses to run in production.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Demo accounts: <role-slug>@demo.example.test / Demo-Pass-2026!
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'Demo-Pass-2026!';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoDataSeeder must never run in production.');
        }
        if (Department::query()->where('code', 'DEMO-ICT')->exists()) {
            $this->command?->warn('Demo data already present - skipped.');

            return;
        }

        $this->call([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class]);

        DB::transaction(function () {
            $users = $this->users();
            Auth::login($users['director']);

            [$ict, $admin, $finance] = $this->departments($users);
            $this->staff($users, $ict, $admin, $finance);
            $projects = $this->projectsAndTasks($users, $ict, $admin);
            $this->documents($users, $ict, $projects[0]);
            $this->correspondence($users, $admin);
            $this->meetings($users, $ict, $projects[0]);
            $suppliers = $this->suppliers();
            $this->assets($users, $ict, $suppliers[0]);
            $this->finance($users, $ict, $projects[0], $suppliers[0]);

            Auth::logout();
        });

        $this->command?->info('Demo data created. Sign in as e.g. director@demo.example.test / '.self::PASSWORD);
    }

    /** @return array<string, User> */
    private function users(): array
    {
        $users = [];
        foreach (Role::query()->get() as $role) {
            $user = User::query()->create([
                'name' => '[DEMO] '.$role->name,
                'email' => $role->slug.'@demo.example.test',
                'password' => self::PASSWORD,
                'status' => 'active',
            ]);
            $user->roles()->attach($role->id, ['assigned_at' => now()]);
            $users[$role->slug] = $user;
        }

        return $users;
    }

    private function departments(array $users): array
    {
        $ict = Department::query()->create(['code' => 'DEMO-ICT', 'name' => '[DEMO] ICT Unit', 'name_so' => '[DEMO] Qaybta ICT', 'head_user_id' => $users['department-head']->id]);
        $admin = Department::query()->create(['code' => 'DEMO-ADM', 'name' => '[DEMO] Administration', 'name_so' => '[DEMO] Maamulka']);
        $finance = Department::query()->create(['code' => 'DEMO-FIN', 'name' => '[DEMO] Finance & Procurement', 'name_so' => '[DEMO] Maaliyadda & Iibsiga']);

        return [$ict, $admin, $finance];
    }

    private function staff(array $users, Department $ict, Department $admin, Department $finance): void
    {
        $map = [
            'director' => [$admin, 'Director'], 'project-manager' => [$ict, 'Project Manager'], 'department-head' => [$ict, 'Head of ICT'],
            'project-officer' => [$ict, 'Project Officer'], 'document-officer' => [$admin, 'Records Officer'],
            'finance-officer' => [$finance, 'Procurement Officer'], 'staff' => [$ict, 'Technical Assistant'], 'system-admin' => [$ict, 'Systems Administrator'],
        ];
        $n = 1;
        foreach ($map as $slug => [$dept, $title]) {
            Staff::query()->create([
                'staff_number' => sprintf('DEMO-%03d', $n++), 'user_id' => $users[$slug]->id, 'department_id' => $dept->id,
                'full_name' => $users[$slug]->name, 'job_title' => '[DEMO] '.$title, 'official_email' => $users[$slug]->email,
                'employment_status' => 'active', 'employment_type' => 'permanent', 'start_date' => today()->subYear(),
            ]);
        }
    }

    private function projectsAndTasks(array $users, Department $ict, Department $admin): array
    {
        $pm = $users['project-manager'];
        $defs = [
            ['[DEMO] Office network upgrade', 'in_progress', $ict, -60, 60],
            ['[DEMO] Records digitisation pilot', 'planned', $admin, 15, 120],
            ['[DEMO] Staff ICT training programme', 'on_hold', $ict, -90, -5],
        ];
        $projects = [];
        foreach ($defs as [$name, $status, $dept, $start, $end]) {
            $projects[] = $project = Project::query()->create([
                'reference' => ReferenceNumber::next('project'), 'name' => $name, 'status' => $status, 'priority' => 'medium',
                'description' => 'Demonstration project for training and testing only.', 'objectives' => 'Illustrate the project workflow.',
                'start_date' => today()->addDays($start), 'expected_end_date' => today()->addDays($end), 'manager_id' => $pm->id, 'department_id' => $dept->id,
            ]);
            $milestone = $project->milestones()->create(['title' => '[DEMO] Requirements agreed', 'due_date' => today()->addDays($start + 20), 'status' => 'completed', 'completed_at' => now()]);
            $project->milestones()->create(['title' => '[DEMO] Implementation complete', 'due_date' => today()->addDays($end - 5), 'status' => 'pending']);
            $project->risks()->create(['type' => 'risk', 'title' => '[DEMO] Supplier delivery delay', 'likelihood' => 'medium', 'impact' => 'high', 'status' => 'open', 'owner_id' => $pm->id, 'corrective_action' => 'Agree delivery dates in writing.']);
            $project->stakeholders()->create(['type' => 'stakeholder', 'name' => '[DEMO] Partner focal point', 'organization' => '[DEMO] Partner institution']);

            foreach ([['todo', 10], ['in_progress', 3], ['completed', -10], ['in_progress', -2]] as $i => [$tStatus, $due]) {
                Task::query()->create([
                    'reference' => ReferenceNumber::next('task'), 'project_id' => $project->id, 'milestone_id' => $milestone->id, 'department_id' => $dept->id,
                    'title' => "[DEMO] Task ".($i + 1)." for {$project->name}", 'priority' => ['low', 'medium', 'high', 'critical'][$i],
                    'status' => $tStatus, 'progress' => $tStatus === 'completed' ? 100 : ($tStatus === 'in_progress' ? 40 : 0),
                    'due_date' => today()->addDays($due), 'assigned_to' => [$users['project-officer'], $users['staff']][$i % 2]->id,
                    'assigned_by' => $pm->id, 'requires_approval' => $i === 1, 'completed_at' => $tStatus === 'completed' ? now() : null,
                ]);
            }
        }

        return $projects;
    }

    private function documents(array $users, Department $ict, Project $project): void
    {
        $category = DocumentCategory::query()->where('code', 'PLN')->first();
        foreach ([['[DEMO] Network upgrade plan', 'internal', 'approved'], ['[DEMO] Server room access list', 'confidential', 'pending_review'], ['[DEMO] Public notice template', 'public', 'draft']] as [$title, $conf, $status]) {
            $doc = Document::query()->create([
                'reference' => ReferenceNumber::next('document'), 'title' => $title, 'category_id' => $category?->id, 'department_id' => $ict->id,
                'project_id' => $project->id, 'owner_id' => $users['document-officer']->id, 'registration_date' => today(), 'confidentiality' => $conf,
                'status' => $status, 'review_date' => today()->addMonths(6), 'expiry_date' => $status === 'approved' ? today()->addDays(20) : null, 'current_version' => 1,
            ]);
            $tmp = tempnam(sys_get_temp_dir(), 'demo');
            file_put_contents($tmp, $this->minimalPdf($title));
            $meta = FileStorage::store(new UploadedFile($tmp, 'demo-document.pdf', 'application/pdf', null, true), 'documents');
            $doc->versions()->create($meta + ['version_number' => 1, 'change_note' => 'Initial demo version', 'uploaded_by' => $users['document-officer']->id]);
            @unlink($tmp);
        }
    }

    private function correspondence(array $users, Department $admin): void
    {
        foreach ([['incoming', '[DEMO] Request for data hosting information', 3], ['incoming', '[DEMO] Invitation to stakeholder workshop', -2], ['outgoing', '[DEMO] Reply on hosting information', null]] as [$dir, $subject, $deadline]) {
            Correspondence::query()->create([
                'reference' => ReferenceNumber::next($dir === 'incoming' ? 'correspondence_in' : 'correspondence_out'), 'direction' => $dir, 'subject' => $subject,
                'sender' => $dir === 'incoming' ? '[DEMO] Partner institution' : '[DEMO] NDC', 'recipient' => $dir === 'incoming' ? '[DEMO] NDC' : '[DEMO] Partner institution',
                'received_or_sent_date' => today()->subDays(5), 'department_id' => $admin->id, 'assigned_to' => $users['document-officer']->id,
                'response_deadline' => $deadline !== null ? today()->addDays($deadline) : null, 'status' => $dir === 'incoming' ? 'assigned' : 'closed', 'priority' => 'normal',
            ]);
        }
    }

    private function meetings(array $users, Department $ict, Project $project): void
    {
        $meeting = Meeting::query()->create([
            'reference' => ReferenceNumber::next('meeting'), 'title' => '[DEMO] Weekly project review', 'meeting_type' => 'project', 'location' => '[DEMO] Meeting room 1',
            'starts_at' => now()->addDays(2)->setTime(10, 0), 'ends_at' => now()->addDays(2)->setTime(11, 0), 'organizer_id' => $users['project-manager']->id,
            'department_id' => $ict->id, 'project_id' => $project->id, 'agenda' => "1. Progress\n2. Risks\n3. Next steps",
        ]);
        foreach (['project-officer', 'staff', 'department-head'] as $slug) {
            $meeting->participants()->create(['user_id' => $users[$slug]->id]);
        }
        $past = Meeting::query()->create([
            'reference' => ReferenceNumber::next('meeting'), 'title' => '[DEMO] Kick-off meeting', 'meeting_type' => 'project', 'location' => '[DEMO] Meeting room 1',
            'starts_at' => now()->subDays(20)->setTime(9, 0), 'status' => 'completed', 'organizer_id' => $users['project-manager']->id, 'project_id' => $project->id,
            'minutes' => 'Demonstration minutes.', 'minutes_recorded_by' => $users['project-manager']->id, 'minutes_recorded_at' => now()->subDays(20),
        ]);
        $past->items()->create(['type' => 'decision', 'description' => '[DEMO] Adopt weekly reporting.']);
        $past->items()->create(['type' => 'action', 'description' => '[DEMO] Circulate project plan.', 'responsible_id' => $users['project-officer']->id, 'due_date' => today()->subDays(10), 'status' => 'open']);
    }

    private function suppliers(): array
    {
        $out = [];
        foreach (['[DEMO] Office Supplies Co.', '[DEMO] Network Services Ltd.'] as $name) {
            $out[] = $s = Supplier::query()->create(['code' => ReferenceNumber::next('supplier'), 'name' => $name, 'email' => 'contact@supplier.example.test', 'status' => 'active', 'services' => 'Demonstration supplier']);
            $s->contracts()->create(['reference' => ReferenceNumber::next('contract'), 'title' => '[DEMO] Framework agreement', 'start_date' => today()->subMonths(10), 'end_date' => today()->addDays(25), 'contract_value' => '5000.00', 'status' => 'active']);
        }

        return $out;
    }

    private function assets(array $users, Department $ict, Supplier $supplier): void
    {
        $location = Location::query()->firstOrCreate(['code' => 'DEMO-R1'], ['name' => '[DEMO] Office room 1', 'building' => '[DEMO] Main building']);
        $category = AssetCategory::query()->where('code', 'CMP')->first();
        $staff = Staff::query()->where('user_id', $users['staff']->id)->first();
        foreach ([['[DEMO] Laptop A', 'in_use', $staff?->id], ['[DEMO] Laptop B', 'in_stock', null], ['[DEMO] Projector', 'under_maintenance', null]] as $i => [$name, $status, $staffId]) {
            Asset::query()->create([
                'asset_tag' => ReferenceNumber::next('asset'), 'name' => $name, 'category_id' => $category->id, 'manufacturer' => '[DEMO]', 'model' => 'Demo model',
                'serial_number' => 'DEMO-SN-'.($i + 1), 'purchase_date' => today()->subMonths(6), 'purchase_cost' => '850.00', 'supplier_id' => $supplier->id,
                'warranty_expiry' => today()->addMonths(18), 'condition' => 'good', 'status' => $status, 'location_id' => $location->id,
                'department_id' => $ict->id, 'assigned_staff_id' => $staffId,
            ]);
        }
    }

    private function finance(array $users, Department $ict, Project $project, Supplier $supplier): void
    {
        $source = FundingSource::query()->firstOrCreate(['code' => 'DEMO-GOV'], ['name' => '[DEMO] Government allocation', 'type' => 'government']);
        $equipment = ExpenseCategory::query()->where('code', 'EQP')->first();
        $services = ExpenseCategory::query()->where('code', 'SRV')->first();

        // Prepared by the finance officer, approved by the director (segregation of duties).
        Auth::login($users['finance-officer']);
        $budget = Budget::query()->create([
            'reference' => ReferenceNumber::next('budget'), 'title' => '[DEMO] Network upgrade budget', 'fiscal_year' => (int) now()->format('Y'),
            'period_start' => now()->startOfYear(), 'period_end' => now()->endOfYear(), 'project_id' => $project->id, 'department_id' => $ict->id,
            'funding_source_id' => $source->id, 'currency' => 'USD', 'status' => 'approved', 'approved_by' => $users['director']->id, 'approved_at' => now(),
        ]);
        $budget->allocations()->create(['expense_category_id' => $equipment->id, 'amount' => '12000.00', 'description' => 'Demo equipment']);
        $budget->allocations()->create(['expense_category_id' => $services->id, 'amount' => '3000.00', 'description' => 'Demo services']);

        foreach ([['2400.00', 'paid'], ['1150.50', 'approved'], ['600.00', 'recorded']] as $i => [$amount, $status]) {
            Expense::query()->create([
                'reference' => ReferenceNumber::next('expense'), 'budget_id' => $budget->id, 'expense_category_id' => $equipment->id, 'supplier_id' => $supplier->id,
                'description' => '[DEMO] Equipment purchase '.($i + 1), 'amount' => $amount, 'currency' => 'USD', 'expense_date' => today()->subDays(10 - $i),
                'status' => $status, 'recorded_by' => $users['finance-officer']->id,
                'approved_by' => $status !== 'recorded' ? $users['director']->id : null, 'approved_at' => $status !== 'recorded' ? now() : null,
                'paid_on' => $status === 'paid' ? today()->subDays(2) : null, 'settled_by' => $status === 'paid' ? $users['finance-officer']->id : null,
            ]);
        }

        Auth::login($users['project-officer']);
        $pr = PurchaseRequest::query()->create([
            'reference' => ReferenceNumber::next('purchase_request'), 'title' => '[DEMO] Network switches', 'justification' => 'Demonstration request.',
            'department_id' => $ict->id, 'project_id' => $project->id, 'budget_id' => $budget->id, 'requested_by' => $users['project-officer']->id,
            'needed_by' => today()->addDays(30), 'currency' => 'USD', 'status' => 'submitted', 'submitted_at' => now(),
        ]);
        $line = Money::multiply('4', '375.25');
        $pr->items()->create(['description' => '[DEMO] 24-port switch', 'quantity' => '4', 'unit' => 'unit', 'estimated_unit_cost' => '375.25', 'line_total' => $line]);
        $pr->update(['estimated_total' => $line]);
        Auth::login($users['director']);
    }

    private function minimalPdf(string $title): string
    {
        $text = preg_replace('/[^A-Za-z0-9 .\-\[\]]/', '', $title);

        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            ."4 0 obj<</Length 60>>stream\nBT /F1 14 Tf 72 760 Td ({$text}) Tj ET\nendstream endobj\n"
            ."5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }
}

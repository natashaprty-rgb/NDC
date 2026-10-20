<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Correspondence;
use App\Models\Department;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Meeting;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Staff;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Support\FileStore;
use App\Support\ReferenceNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sample records for demonstrations, training and user acceptance testing.
 * Every record is marked "(sample)" and the seeder refuses to run in
 * production, so sample data can never be mistaken for operational data.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * One user per default role is created with the e-mail
 * {role-slug}@demo.ndc.local and the password in DEMO_PASSWORD (or a random
 * one, printed once).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoDataSeeder must not be run in production.');
        }

        $this->call([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class]);

        $password = env('DEMO_PASSWORD') ?: Str::password(14);

        $departments = collect([
            ['ICT', 'Information & Communication Technology', 'Teknoolajiyadda Xogta iyo Isgaarsiinta'],
            ['ADM', 'Administration & Finance', 'Maamulka iyo Maaliyadda'],
            ['INF', 'Infrastructure & Facilities', 'Kaabayaasha iyo Xarumaha'],
            ['DIR', 'Office of the Director', 'Xafiiska Agaasimaha'],
        ])->mapWithKeys(fn ($d) => [$d[0] => Department::query()->firstOrCreate(['code' => $d[0]], ['name' => $d[1].' (sample)', 'name_so' => $d[2], 'is_active' => true])]);

        $roleDept = [
            'Super Administrator' => 'ICT', 'System Administrator' => 'ICT', 'Director or Management' => 'DIR',
            'Project Manager' => 'INF', 'Department Head' => 'ICT', 'Project Officer' => 'INF', 'Document Officer' => 'ADM',
            'Finance or Procurement Officer' => 'ADM', 'General Staff' => 'ICT', 'Read-Only Auditor' => 'DIR',
        ];

        $users = collect();
        foreach ($roleDept as $role => $dept) {
            $slug = Str::slug($role);
            $user = User::query()->firstOrCreate(['email' => "{$slug}@demo.ndc.local"], [
                'name' => 'Demo '.$role, 'password' => $password, 'status' => 'active', 'locale' => 'en',
                'department_id' => $departments[$dept]->id, 'job_title' => $role,
            ]);
            $user->forceFill(['password_changed_at' => now(), 'email_verified_at' => now()])->save();
            $user->syncRoles([$role]);
            $users[$role] = $user;
        }

        foreach ($users->values() as $i => $user) {
            Staff::query()->firstOrCreate(['user_id' => $user->id], [
                'staff_number' => sprintf('DEMO-%03d', $i + 1), 'full_name' => $user->name, 'job_title' => $user->job_title,
                'department_id' => $user->department_id, 'email' => $user->email, 'employment_status' => 'active',
                'employment_type' => 'permanent', 'hire_date' => today()->subYears(1),
            ]);
        }

        $pm = $users['Project Manager'];
        $project = Project::query()->firstOrCreate(['code' => 'DEMO-PRJ-1'], [
            'title' => 'Data center facility design (sample)',
            'description' => 'Sample project used for training.',
            'objectives' => "Agree the facility design\nPrepare tender documents",
            'location' => 'Mogadishu', 'department_id' => $departments['INF']->id, 'manager_id' => $pm->id,
            'status' => 'in_progress', 'priority' => 'high', 'start_date' => today()->subMonths(2),
            'expected_end_date' => today()->addMonths(6), 'planned_budget' => 250000, 'currency' => 'USD', 'progress' => 30,
            'created_by' => $pm->id,
        ]);
        $milestone = $project->milestones()->firstOrCreate(['title' => 'Concept design approved (sample)'], ['due_date' => today()->addWeeks(3), 'status' => 'in_progress']);
        $project->risks()->firstOrCreate(['title' => 'Delayed site survey (sample)'], ['type' => 'risk', 'likelihood' => 'medium', 'impact' => 'high', 'status' => 'open', 'corrective_action' => 'Engage second surveyor']);
        $project->members()->syncWithoutDetaching([$users['Project Officer']->id => ['role' => 'Officer']]);

        Task::query()->firstOrCreate(['title' => 'Collect site survey reports (sample)'], [
            'project_id' => $project->id, 'milestone_id' => $milestone->id, 'assigned_to' => $users['Project Officer']->id,
            'created_by' => $pm->id, 'priority' => 'high', 'status' => 'in_progress', 'progress' => 50, 'due_date' => today()->addDays(2),
        ]);
        Task::query()->firstOrCreate(['title' => 'Update staff contact list (sample)'], [
            'assigned_to' => $users['General Staff']->id, 'department_id' => $departments['ICT']->id,
            'created_by' => $users['Department Head']->id, 'priority' => 'medium', 'status' => 'pending', 'due_date' => today()->subDay(),
        ]);

        if (! Document::query()->where('title', 'ICT acceptable use policy (sample)')->exists()) {
            $doc = Document::query()->create([
                'reference_number' => ReferenceNumber::next('DOC'), 'title' => 'ICT acceptable use policy (sample)',
                'category_id' => Category::query()->ofType('document')->where('name', 'Policy')->value('id'),
                'department_id' => $departments['ICT']->id, 'owner_id' => $users['Document Officer']->id,
                'confidentiality' => 'internal', 'status' => 'published', 'document_date' => today()->subMonth(),
                'review_date' => today()->addMonths(11), 'created_by' => $users['Document Officer']->id,
            ]);
            $path = 'documents/demo/'.Str::uuid().'.txt';
            Storage::disk(FileStore::DISK)->put($path, "Sample document for training.\n");
            $doc->versions()->create([
                'version' => 1, 'original_name' => 'acceptable-use-policy.txt', 'disk' => FileStore::DISK, 'path' => $path,
                'mime_type' => 'text/plain', 'size' => Storage::disk(FileStore::DISK)->size($path),
                'checksum' => hash('sha256', Storage::disk(FileStore::DISK)->get($path)), 'uploaded_by' => $users['Document Officer']->id,
            ]);
            $doc->forceFill(['current_version' => 1, 'published_at' => now()])->save();
        }

        if (! Correspondence::query()->where('subject', 'Request for data hosting information (sample)')->exists()) {
            Correspondence::query()->create([
                'reference_number' => ReferenceNumber::next('IN'), 'direction' => 'incoming', 'external_reference' => 'MOF/2026/118',
                'subject' => 'Request for data hosting information (sample)', 'sender' => 'Ministry of Finance (sample)',
                'recipient' => 'Director, NDC', 'letter_date' => today()->subDays(5), 'received_or_sent_date' => today()->subDays(4),
                'department_id' => $departments['DIR']->id, 'assigned_to' => $users['Department Head']->id, 'status' => 'received',
                'priority' => 'normal', 'response_due_date' => today()->addDays(5), 'created_by' => $users['Document Officer']->id,
            ]);
        }

        $meeting = Meeting::query()->firstOrCreate(['title' => 'Monthly management meeting (sample)'], [
            'type' => 'meeting', 'start_at' => now()->addDays(3)->setTime(10, 0), 'end_at' => now()->addDays(3)->setTime(11, 30),
            'location' => 'NDC Conference Room', 'organizer_id' => $users['Director or Management']->id,
            'department_id' => $departments['DIR']->id, 'status' => 'scheduled',
            'agenda' => "1. Project updates\n2. Procurement status\n3. Any other business", 'created_by' => $users['Director or Management']->id,
        ]);
        foreach (['Project Manager', 'Department Head', 'Finance or Procurement Officer'] as $role) {
            $meeting->participants()->firstOrCreate(['user_id' => $users[$role]->id], ['attendance' => 'invited']);
        }

        $supplier = Supplier::query()->firstOrCreate(['name' => 'Horn ICT Supplies (sample)'], [
            'type' => 'supplier', 'category_id' => Category::query()->ofType('supplier')->where('name', 'ICT Equipment')->value('id'),
            'contact_person' => 'Sample Contact', 'email' => 'sales@supplier.example', 'phone' => '+252 61 000 0000', 'status' => 'active',
        ]);

        Asset::query()->firstOrCreate(['asset_tag' => 'DEMO-AST-0001'], [
            'name' => 'Laptop (sample)', 'category_id' => Category::query()->ofType('asset')->where('name', 'Laptop')->value('id'),
            'manufacturer' => 'Sample', 'model' => 'X1', 'serial_number' => 'SN-DEMO-1', 'supplier_id' => $supplier->id,
            'purchase_date' => today()->subMonths(10), 'purchase_cost' => 1200, 'warranty_expiry' => today()->addDays(20),
            'location' => 'HQ, Room 2', 'department_id' => $departments['ICT']->id, 'condition' => 'good', 'status' => 'available',
        ]);

        $budget = Budget::query()->firstOrCreate(['code' => 'DEMO-BUD-1'], [
            'name' => 'Facility design budget (sample)', 'fiscal_year' => now()->format('Y'), 'project_id' => $project->id,
            'amount' => 250000, 'currency' => 'USD', 'status' => 'approved', 'created_by' => $users['Finance or Procurement Officer']->id,
        ]);
        $consultancy = Category::query()->ofType('expense')->where('name', 'Consultancy')->value('id');
        $budget->allocations()->firstOrCreate(['category_id' => $consultancy], ['planned_amount' => 150000, 'description' => 'Design consultancy']);
        Expense::query()->firstOrCreate(['reference' => 'DEMO-INV-1'], [
            'budget_id' => $budget->id, 'category_id' => $consultancy, 'description' => 'Concept design, first payment (sample)',
            'payee' => 'Sample Consultants', 'amount' => 30000, 'expense_date' => today()->subWeeks(2), 'status' => 'approved',
            'recorded_by' => $users['Finance or Procurement Officer']->id,
        ]);

        if (! ProcurementRequest::query()->where('title', 'Laptops for project team (sample)')->exists()) {
            $request = ProcurementRequest::query()->create([
                'reference_number' => ReferenceNumber::next('PR'), 'title' => 'Laptops for project team (sample)',
                'justification' => 'Sample purchase request.', 'department_id' => $departments['INF']->id, 'project_id' => $project->id,
                'budget_id' => $budget->id, 'requested_by' => $pm->id, 'needed_by' => today()->addMonth(), 'status' => 'draft', 'currency' => 'USD',
            ]);
            $request->items()->create(['description' => 'Laptop, 16 GB RAM', 'quantity' => 3, 'unit' => 'unit', 'estimated_unit_cost' => 1100]);
            $request->recalculateTotal();
        }

        $this->command?->warn('Sample data created. Demo accounts: {role}@demo.ndc.local (e.g. super-administrator@demo.ndc.local)');
        if (! env('DEMO_PASSWORD')) {
            $this->command?->warn("Demo password (shown once): {$password}");
        }
    }
}

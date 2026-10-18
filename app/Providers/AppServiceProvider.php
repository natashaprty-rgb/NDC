<?php

namespace App\Providers;

use App\Listeners\RecordAuthenticationActivity;
use App\Listeners\RecordNotificationDelivery;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // created_by / updated_by columns used across the schema
        Blueprint::macro('userstamps', function () {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Short, stable type names stored in polymorphic columns (attachments, audit_logs, notifications)
        Relation::morphMap([
            'user' => \App\Models\User::class,
            'role' => \App\Models\Role::class,
            'setting' => \App\Models\Setting::class,
            'department' => \App\Models\Department::class,
            'staff' => \App\Models\Staff::class,
            'project' => \App\Models\Project::class,
            'project_stakeholder' => \App\Models\ProjectStakeholder::class,
            'project_milestone' => \App\Models\ProjectMilestone::class,
            'project_risk' => \App\Models\ProjectRisk::class,
            'task' => \App\Models\Task::class,
            'document_category' => \App\Models\DocumentCategory::class,
            'document' => \App\Models\Document::class,
            'document_access' => \App\Models\DocumentAccess::class,
            'correspondence' => \App\Models\Correspondence::class,
            'meeting' => \App\Models\Meeting::class,
            'meeting_item' => \App\Models\MeetingItem::class,
            'supplier' => \App\Models\Supplier::class,
            'supplier_contract' => \App\Models\SupplierContract::class,
            'purchase_request' => \App\Models\PurchaseRequest::class,
            'quotation' => \App\Models\Quotation::class,
            'purchase_order' => \App\Models\PurchaseOrder::class,
            'goods_receipt' => \App\Models\GoodsReceipt::class,
            'funding_source' => \App\Models\FundingSource::class,
            'expense_category' => \App\Models\ExpenseCategory::class,
            'budget' => \App\Models\Budget::class,
            'budget_allocation' => \App\Models\BudgetAllocation::class,
            'expense' => \App\Models\Expense::class,
            'asset_category' => \App\Models\AssetCategory::class,
            'location' => \App\Models\Location::class,
            'asset' => \App\Models\Asset::class,
            'asset_maintenance' => \App\Models\AssetMaintenance::class,
            'permission_review' => \App\Models\PermissionReview::class,
            'backup_record' => \App\Models\BackupRecord::class,
        ]);

        // Carbon's bundled Somali locale omits "Maarso" (March), shifting every later month. Correct it.
        \Carbon\Translator::get('so')->setTranslations([
            'months' => ['Janaayo', 'Febraayo', 'Maarso', 'Abriil', 'Maajo', 'Juun', 'Luuliyo', 'Agoosto', 'Sebteembar', 'Oktoobar', 'Nofeembar', 'Diseembar'],
            'months_short' => ['Jan', 'Feb', 'Mar', 'Abr', 'Mjo', 'Jun', 'Lyo', 'Agt', 'Seb', 'Okt', 'Nof', 'Dis'],
        ]);

        Paginator::defaultView('components.pagination');

        Password::defaults(fn () => Password::min(10)->letters()->mixedCase()->numbers()->symbols());

        $this->registerPermissionGates();
        $this->registerRateLimiters();

        Event::listen([Login::class, Logout::class, Failed::class, Lockout::class, PasswordReset::class], RecordAuthenticationActivity::class);
        Event::listen([NotificationSent::class, NotificationFailed::class], RecordNotificationDelivery::class);
    }

    /**
     * Every permission slug in config/ndc_permissions.php becomes a Gate ability,
     * so routes can use `can:projects.view` and Blade can use `@can('projects.view')`.
     * Record-level rules (ownership, segregation of duties) live in Policies and
     * apply to every user, including Super Administrators.
     */
    private function registerPermissionGates(): void
    {
        $catalog = collect(config('ndc_permissions.permissions'))->flatMap(fn ($group) => $group)->all();

        Gate::before(function (User $user, string $ability) use ($catalog) {
            if (! array_key_exists($ability, $catalog)) {
                return null; // let policies decide
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }

    private function registerRateLimiters(): void
    {
        // IP-wide backstop. Per-account lockout (5 failures / 5 minutes) is enforced in LoginController.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(6)->by(($request->user()?->id ?? 'guest').'|'.$request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)->by((string) $request->user()?->id ?: $request->ip()));

        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id ?: $request->ip()));
    }
}

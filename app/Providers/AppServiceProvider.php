<?php

namespace App\Providers;

use App\Models;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Stable aliases stored in polymorphic columns (audit log, attachments,
     * approvals) instead of PHP class names.
     *
     * @var array<string, class-string<Model>>
     */
    public const MORPH_MAP = [
        'user' => Models\User::class,
        'department' => Models\Department::class,
        'category' => Models\Category::class,
        'staff' => Models\Staff::class,
        'staff_training' => Models\StaffTraining::class,
        'project' => Models\Project::class,
        'project_milestone' => Models\ProjectMilestone::class,
        'project_risk' => Models\ProjectRisk::class,
        'project_stakeholder' => Models\ProjectStakeholder::class,
        'task' => Models\Task::class,
        'task_comment' => Models\TaskComment::class,
        'document' => Models\Document::class,
        'correspondence' => Models\Correspondence::class,
        'meeting' => Models\Meeting::class,
        'meeting_action_item' => Models\MeetingActionItem::class,
        'asset' => Models\Asset::class,
        'asset_maintenance' => Models\AssetMaintenance::class,
        'asset_disposal' => Models\AssetDisposal::class,
        'supplier' => Models\Supplier::class,
        'procurement_request' => Models\ProcurementRequest::class,
        'quotation' => Models\Quotation::class,
        'purchase_order' => Models\PurchaseOrder::class,
        'goods_receipt' => Models\GoodsReceipt::class,
        'budget' => Models\Budget::class,
        'budget_allocation' => Models\BudgetAllocation::class,
        'expense' => Models\Expense::class,
        'setting' => Models\Setting::class,
        'role' => Role::class,
    ];

    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(self::MORPH_MAP);

        // Each module keeps its interface translations in lang/modules/{module}/{locale}.json.
        foreach (glob(lang_path('modules/*'), GLOB_ONLYDIR) ?: [] as $path) {
            $this->app['translator']->addJsonPath($path);
        }

        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Super Administrators hold every permission ("module.action"
        // abilities). Policy abilities ("update", "delete", ...) still run, so
        // record status rules such as "an approved budget cannot be edited"
        // apply to them too.
        Gate::before(function (Models\User $user, string $ability) {
            return str_contains($ability, '.') && $user->isActive() && $user->isSuperAdmin() ? true : null;
        });

        Password::defaults(function () {
            return Password::min((int) setting('password_min_length', 10))
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        });

        Paginator::defaultView('components.pagination');

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
        $this->registerAuthAuditing();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    private function registerAuthAuditing(): void
    {
        Event::listen(Login::class, function (Login $event) {
            $event->user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
            ])->saveQuietly();
            Audit::log('login', $event->user, __('Signed in'), userId: $event->user->getAuthIdentifier());
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                Audit::log('logout', $event->user, __('Signed out'), userId: $event->user->getAuthIdentifier());
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            Audit::log('login_failed', $event->user, mb_substr((string) ($event->credentials['email'] ?? ''), 0, 200));
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event) {
            Audit::log('password_reset', $event->user, __('Password reset'), userId: $event->user->getAuthIdentifier());
        });
    }
}

<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Department;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Support\FileStore;
use App\Support\ReferenceNumber;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Document register: upload, registration, search and metadata (SRS 3.5).
 * Versions, workflow and access grants live in their own controllers.
 */
class DocumentController extends Controller
{
    private const SORTABLE = ['reference_number', 'title', 'document_date', 'review_date', 'expiry_date', 'status', 'confidentiality', 'updated_at', 'created_at'];

    /** Maximum rows on the printable register. */
    private const REGISTER_LIMIT = 2000;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Document::class);

        $query = $this->filtered($request)->with(['category', 'department', 'owner']);
        $this->applySort($query, $request, self::SORTABLE, 'updated_at');

        return view('documents.index', [
            'documents' => $query->paginate($this->perPage($request))->withQueryString(),
            'expiryDays' => $this->expiryDays(),
        ] + $this->filterOptions());
    }

    /**
     * Printable register of the documents matching the current filters.
     */
    public function register(Request $request): View
    {
        $this->authorize('viewAny', Document::class);

        $query = $this->filtered($request)->with(['category', 'department', 'project', 'owner']);
        $this->applySort($query, $request, self::SORTABLE, 'reference_number', 'asc');

        $total = (clone $query)->count();

        return view('documents.register', [
            'documents' => $query->limit(self::REGISTER_LIMIT)->get(),
            'total' => $total,
            'limit' => self::REGISTER_LIMIT,
            'expiryDays' => $this->expiryDays(),
        ] + $this->filterOptions());
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Document::class);

        return view('documents.form', $this->formData(new Document([
            'confidentiality' => 'internal',
            'owner_id' => $request->user()->id,
            'department_id' => $request->user()->department_id,
            'project_id' => $request->integer('project') ?: null,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $data = $request->validate($this->rules($request) + [
            'reference_number' => ['nullable', 'string', 'max:60', Rule::unique('documents', 'reference_number')],
            'file' => FileStore::rules(required: true),
            'change_summary' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $meta = FileStore::put($request->file('file'), 'documents');

        try {
            $document = DB::transaction(function () use ($data, $meta, $user) {
                $document = new Document([
                    ...collect($data)->except(['file', 'change_summary'])->all(),
                    'reference_number' => filled($data['reference_number'] ?? null) ? $data['reference_number'] : ReferenceNumber::next('DOC'),
                    'owner_id' => $data['owner_id'] ?? $user->id,
                    'status' => 'draft',
                    'created_by' => $user->id,
                ]);
                $document->forceFill(['current_version' => 1])->save();

                $document->versions()->create($meta + [
                    'version' => 1,
                    'change_summary' => $data['change_summary'] ?? __('Initial version'),
                    'uploaded_by' => $user->id,
                ]);

                return $document;
            });
        } catch (Throwable $e) {
            FileStore::delete($meta['disk'], $meta['path']);

            throw $e;
        }

        return redirect()->route('documents.show', $document)->with('success', __('Document registered.'));
    }

    public function show(Request $request, Document $document): View
    {
        $this->authorize('view', $document);
        $user = $request->user();

        $document->logAccess('view', $user, null, $request->ip());
        $document->load(['category', 'department', 'project', 'owner', 'creator', 'versions.uploader', 'grantedUsers']);

        $canManageAccess = $user->can('manageAccess', $document);
        $canViewLog = $user->can('viewAccessLog', $document);

        return view('documents.show', [
            'document' => $document,
            'expiryDays' => $this->expiryDays(),
            'canManageAccess' => $canManageAccess,
            'canViewLog' => $canViewLog,
            'accessLogs' => $canViewLog ? $document->accessLogs()->with(['user', 'version'])->limit(50)->get() : collect(),
            'grantableUsers' => $canManageAccess ? $this->grantableUsers($document) : collect(),
            'grantors' => $canManageAccess
                ? User::query()->whereIn('id', $document->grantedUsers->pluck('pivot.granted_by')->filter()->unique())->pluck('name', 'id')
                : collect(),
        ]);
    }

    public function edit(Document $document): View
    {
        $this->authorize('update', $document);

        return view('documents.form', $this->formData($document));
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $data = $request->validate($this->rules($request));
        $data['owner_id'] ??= $document->owner_id ?? $request->user()->id;

        $document->update($data);

        return redirect()->route('documents.show', $document)->with('success', __('Document details updated.'));
    }

    /**
     * Register filters shared by the list and the printable register.
     */
    private function filtered(Request $request): Builder
    {
        $days = $this->expiryDays();

        return Document::query()
            ->visibleTo($request->user())
            ->search($request->query('q'))
            ->when($request->query('category'), fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($request->query('department'), fn (Builder $q, $v) => $q->where('department_id', $v))
            ->when($request->query('project'), fn (Builder $q, $v) => $q->where('project_id', $v))
            ->when($request->query('confidentiality'), fn (Builder $q, $v) => $q->where('confidentiality', $v))
            ->when($request->query('status'), fn (Builder $q, $v) => $q->where('status', $v))
            ->when(
                $request->boolean('archived'),
                fn (Builder $q) => $q->whereNotNull('archived_at'),
                fn (Builder $q) => $q->active(),
            )
            ->when($request->query('expiring') === 'soon', fn (Builder $q) => $q->expiringWithin($days))
            ->when($request->query('expiring') === 'expired', fn (Builder $q) => $q->expired())
            ->when($request->query('expiring') === 'review', fn (Builder $q) => $q->whereNotNull('review_date')->whereDate('review_date', '<=', today()));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('type', 'document')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'confidentiality' => ['required', Rule::in(Status::keys('confidentiality'))],
            'document_date' => ['nullable', 'date'],
            'review_date' => ['nullable', 'date'],
            'expiry_date' => array_filter(['nullable', 'date', $request->filled('document_date') ? 'after_or_equal:document_date' : null]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'categories' => Category::options('document'),
            'departments' => Department::options(),
            'projects' => Project::options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Document $document): array
    {
        $projects = Project::options();
        if ($document->project_id && ! isset($projects[$document->project_id]) && $document->project) {
            $projects[$document->project_id] = $document->project->code.' — '.$document->project->title;
        }

        return [
            'document' => $document,
            'categories' => Category::options('document', $document->category_id),
            'departments' => Department::options(),
            'projects' => $projects,
            'owners' => User::query()
                ->where(fn (Builder $q) => $q->active()->when($document->owner_id, fn (Builder $q, $id) => $q->orWhere('id', $id)))
                ->orderBy('name')->pluck('name', 'id'),
        ];
    }

    /**
     * Active users with documents.view who do not already have access.
     *
     * @return Collection<int, string>
     */
    private function grantableUsers(Document $document)
    {
        $exclude = $document->grantedUsers->pluck('id')->push($document->owner_id, $document->created_by)->filter()->all();

        return User::query()->active()->whereNotIn('id', $exclude)->orderBy('name')->get()
            ->filter(fn (User $u) => $u->can('documents.view') && ! $u->can('documents.view-confidential'))
            ->mapWithKeys(fn (User $u) => [$u->id => $u->name.' <'.$u->email.'>']);
    }

    private function expiryDays(): int
    {
        return max(1, (int) setting('document_expiry_reminder_days', 30));
    }
}

<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\DocumentRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Project;
use App\Models\User;
use App\Services\Documents\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Document::class);
        $user = $request->user();

        $documents = Document::query()
            ->visibleTo($user)
            ->with(['category', 'department', 'owner'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('reference', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->integer('project')))
            ->when(in_array($request->query('confidentiality'), Document::CONFIDENTIALITY, true), fn ($q) => $q->where('confidentiality', $request->query('confidentiality')))
            ->when(
                in_array($request->query('status'), Document::STATUSES, true),
                fn ($q) => $q->where('status', $request->query('status')),
                fn ($q) => $q->where('status', '!=', 'archived')
            )
            ->when($request->boolean('expiring'), fn ($q) => $q->whereNotNull('expiry_date')->whereBetween('expiry_date', [today(), today()->addDays(30)]))
            ->when($request->boolean('review_due'), fn ($q) => $q->whereNotNull('review_date')->whereDate('review_date', '<=', today()))
            ->latest('registration_date')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'categories' => DocumentCategory::query()->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all(),
            'departments' => Department::query()->orderBy('name')->get()->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Document::class);

        $document = new Document([
            'registration_date' => today(),
            'confidentiality' => 'internal',
            'owner_id' => $request->user()->id,
            'department_id' => $request->user()->departmentId(),
            'project_id' => $request->integer('project') ?: null,
        ]);

        return view('documents.form', ['document' => $document] + $this->formOptions());
    }

    public function store(DocumentRequest $request): RedirectResponse
    {
        $document = $this->service->register($request->validated(), $request->file('file'), $request->user());

        return redirect()->route('documents.show', $document)->with('success', __('documents.registered', ['reference' => $document->reference]));
    }

    public function show(Document $document): View
    {
        $this->authorize('view', $document);

        $document->load(['category', 'department', 'project', 'owner', 'approvedBy', 'versions.uploader', 'accessGrants.user', 'accessGrants.department']);

        return view('documents.show', [
            'document' => $document,
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'departments' => Department::query()->orderBy('name')->get()->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all(),
        ]);
    }

    public function edit(Document $document): View
    {
        $this->authorize('update', $document);

        return view('documents.form', ['document' => $document] + $this->formOptions());
    }

    public function update(DocumentRequest $request, Document $document): RedirectResponse
    {
        $data = $request->validated();
        $data['retain_until'] = $this->service->retainUntil($data['category_id'] ?? null, $data['registration_date']);
        $data['owner_id'] = $data['owner_id'] ?? $document->owner_id;
        $document->update($data);

        return redirect()->route('documents.show', $document)->with('success', __('common.updated'));
    }

    private function formOptions(): array
    {
        return [
            'categories' => DocumentCategory::query()->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all(),
            'departments' => Department::query()->active()->orderBy('name')->get()->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all(),
            'projects' => Project::query()->notArchived()->orderBy('name')->pluck('name', 'id')->all(),
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}

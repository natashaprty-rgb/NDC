<?php

namespace App\Http\Controllers\Correspondence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Correspondence\CorrespondenceRequest;
use App\Models\Correspondence;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Services\Correspondence\CorrespondenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CorrespondenceController extends Controller
{
    public function __construct(private readonly CorrespondenceService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Correspondence::class);

        $status = $request->query('status');
        $items = Correspondence::query()
            ->visibleTo($request->user())
            ->with(['department', 'assignee'])
            ->when(in_array($request->query('direction'), Correspondence::DIRECTIONS, true), fn ($q) => $q->where('direction', $request->query('direction')))
            ->when($status === 'open', fn ($q) => $q->whereIn('status', Correspondence::OPEN_STATUSES))
            ->when(in_array($status, Correspondence::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('assigned'), fn ($q) => $q->where('assigned_to', $request->integer('assigned')))
            ->when($request->filled('from') && strtotime((string) $request->query('from')), fn ($q) => $q->whereDate('received_or_sent_date', '>=', $request->query('from')))
            ->when($request->filled('to') && strtotime((string) $request->query('to')), fn ($q) => $q->whereDate('received_or_sent_date', '<=', $request->query('to')))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereIn('status', Correspondence::OPEN_STATUSES)->whereNotNull('response_deadline')->whereDate('response_deadline', '<', today()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('subject', 'like', $term)->orWhere('reference', 'like', $term)
                    ->orWhere('external_reference', 'like', $term)->orWhere('sender', 'like', $term)->orWhere('recipient', 'like', $term));
            })
            ->latest('received_or_sent_date')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('correspondence.index', [
            'items' => $items,
            'departments' => $this->departments(),
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Correspondence::class);

        $item = new Correspondence([
            'direction' => in_array($request->query('direction'), Correspondence::DIRECTIONS, true) ? $request->query('direction') : 'incoming',
            'received_or_sent_date' => today(),
            'priority' => 'normal',
            'confidentiality' => 'internal',
            'in_reply_to_id' => $request->integer('reply_to') ?: null,
        ]);

        return view('correspondence.form', ['item' => $item] + $this->formOptions($request));
    }

    public function store(CorrespondenceRequest $request): RedirectResponse
    {
        $item = $this->service->register($request->validated(), $request->file('file'), $request->user());

        return redirect()->route('correspondence.show', $item)->with('success', __('correspondence.registered', ['reference' => $item->reference]));
    }

    public function show(Request $request, Correspondence $correspondence): View
    {
        $this->authorize('view', $correspondence);

        $correspondence->load(['department', 'assignee', 'project', 'inReplyTo', 'actions.fromUser', 'actions.toUser', 'actions.toDepartment', 'creator']);
        $replies = $correspondence->replies()->visibleTo($request->user())->get();

        return view('correspondence.show', [
            'item' => $correspondence,
            'replies' => $replies,
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'departments' => $this->departments(),
        ]);
    }

    public function edit(Request $request, Correspondence $correspondence): View
    {
        $this->authorize('update', $correspondence);

        return view('correspondence.form', ['item' => $correspondence] + $this->formOptions($request, $correspondence));
    }

    public function update(CorrespondenceRequest $request, Correspondence $correspondence): RedirectResponse
    {
        $correspondence->update($request->validated());

        return redirect()->route('correspondence.show', $correspondence)->with('success', __('common.updated'));
    }

    public function destroy(Correspondence $correspondence): RedirectResponse
    {
        $this->authorize('delete', $correspondence);

        $correspondence->delete();

        return redirect()->route('correspondence.index')->with('success', __('common.deleted'));
    }

    private function departments(): array
    {
        return Department::query()->orderBy('name')->get()->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all();
    }

    private function formOptions(Request $request, ?Correspondence $current = null): array
    {
        return [
            'departments' => $this->departments(),
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'projects' => Project::query()->notArchived()->orderBy('name')->pluck('name', 'id')->all(),
            'replyOptions' => Correspondence::query()->visibleTo($request->user())
                ->when($current, fn ($q) => $q->whereKeyNot($current->id))
                ->latest('id')->limit(200)->get(['id', 'reference', 'subject'])
                ->mapWithKeys(fn ($c) => [$c->id => $c->reference.' — '.\Illuminate\Support\Str::limit($c->subject, 60)])->all(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Correspondence;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use App\Models\Department;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\FileStore;
use App\Support\ReferenceNumber;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Incoming and outgoing official letters: registers, search and records.
 * Workflow actions (assign, forward, respond, send, close) live in
 * CorrespondenceActionController.
 */
class CorrespondenceController extends Controller
{
    /** Maximum rows on the printable register. */
    private const REGISTER_LIMIT = 1000;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Correspondence::class);
        $user = $request->user();

        $query = $this->filtered(Correspondence::query()->visibleTo($user), $request)
            ->with(['assignee', 'department']);
        $this->applySort($query, $request, ['reference_number', 'subject', 'received_or_sent_date', 'response_due_date', 'status', 'created_at'], 'created_at');

        $base = Correspondence::query()->visibleTo($user);

        return view('correspondence.index', [
            'letters' => $query->paginate($this->perPage($request))->withQueryString(),
            'departments' => Department::options(),
            'users' => self::assignableUsers(),
            'counts' => [
                'open' => (clone $base)->open()->count(),
                'overdue' => (clone $base)->overdue()->count(),
                'mine' => (clone $base)->open()->where('assigned_to', $user->id)->count(),
            ],
        ]);
    }

    /**
     * Printable incoming or outgoing register for a date range.
     */
    public function register(Request $request): View
    {
        $this->authorize('viewAny', Correspondence::class);

        $data = $request->validate([
            'direction' => ['nullable', Rule::in(Status::keys('correspondence_direction'))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $direction = $data['direction'] ?? 'incoming';

        $query = Correspondence::query()->visibleTo($request->user())
            ->where('direction', $direction)
            ->with(['assignee', 'department'])
            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('received_or_sent_date', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('received_or_sent_date', '<=', $d))
            ->orderBy('received_or_sent_date')
            ->orderBy('reference_number');

        $total = (clone $query)->count();

        return view('correspondence.register', [
            'letters' => $query->limit(self::REGISTER_LIMIT)->get(),
            'total' => $total,
            'limit' => self::REGISTER_LIMIT,
            'direction' => $direction,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Correspondence::class);

        $direction = in_array($request->query('direction'), Status::keys('correspondence_direction'), true)
            ? $request->query('direction') : 'incoming';
        $organisation = (string) setting('organisation_name', config('ndc.organisation'));

        $letter = new Correspondence([
            'direction' => $direction,
            'priority' => 'normal',
            'is_confidential' => false,
            'department_id' => $request->user()->department_id,
            'responsible_officer_id' => $request->user()->id,
            'sender' => $direction === 'outgoing' ? $organisation : null,
            'recipient' => $direction === 'incoming' ? $organisation : null,
            'received_or_sent_date' => today(),
        ]);

        return view('correspondence.form', $this->formData($letter));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Correspondence::class);
        $data = $this->validated($request);
        $actor = $request->user();

        $letter = DB::transaction(function () use ($data, $request, $actor) {
            $prefix = $data['direction'] === 'incoming' ? 'IN' : 'OUT';

            $letter = Correspondence::query()->create([
                ...collect($data)->except(['scan', 'assignment_note'])->all(),
                'reference_number' => ReferenceNumber::next($prefix),
                'status' => $data['direction'] === 'incoming' ? 'received' : 'pending',
                'created_by' => $actor->id,
            ]);

            $letter->recordAction('registered', $actor);

            if ($request->hasFile('scan')) {
                FileStore::attach($letter, $request->file('scan'), 'default', __('Scanned letter'));
            }

            if (! empty($data['assigned_to'])) {
                $letter->recordAction('assigned', $actor, (int) $data['assigned_to'], $data['assignment_note'] ?? null, $data['response_due_date'] ?? null);
            }

            return $letter;
        });

        if ($letter->assigned_to) {
            $this->notifyAssignee($letter, $actor);
        }

        return redirect()->route('correspondence.show', $letter)
            ->with('success', __('Letter registered with reference :ref.', ['ref' => $letter->reference_number]));
    }

    public function show(Correspondence $correspondence): View
    {
        $this->authorize('view', $correspondence);

        $correspondence->load([
            'department', 'responsibleOfficer', 'assignee', 'creator', 'attachments.uploader',
            'actions.user', 'actions.toUser', 'parent',
        ]);
        $user = request()->user();

        return view('correspondence.show', [
            'letter' => $correspondence,
            'replies' => $correspondence->replies()->visibleTo($user)->orderBy('created_at')->get(),
            'parentVisible' => $correspondence->parent?->isVisibleTo($user) ?? false,
            'users' => self::assignableUsers(),
            'replyOptions' => $correspondence->direction === 'incoming'
                ? Correspondence::query()->visibleTo($user)->where('direction', 'outgoing')
                    ->whereNull('parent_id')->whereKeyNot($correspondence->id)
                    ->latest()->limit(200)->get()
                    ->mapWithKeys(fn (Correspondence $c) => [$c->id => $c->reference_number.' — '.$c->subject])->all()
                : [],
        ]);
    }

    public function edit(Correspondence $correspondence): View
    {
        $this->authorize('update', $correspondence);

        return view('correspondence.form', $this->formData($correspondence));
    }

    public function update(Request $request, Correspondence $correspondence): RedirectResponse
    {
        $this->authorize('update', $correspondence);
        $data = $this->validated($request, $correspondence);

        $correspondence->update($data);

        return redirect()->route('correspondence.show', $correspondence)->with('success', __('Letter updated.'));
    }

    public function destroy(Correspondence $correspondence): RedirectResponse
    {
        $this->authorize('delete', $correspondence);

        $correspondence->delete();

        return redirect()->route('correspondence.index')->with('success', __('Letter deleted.'));
    }

    /**
     * Search and filters shared by the list.
     */
    private function filtered(Builder $query, Request $request): Builder
    {
        $direction = $request->query('direction');
        $status = $request->query('status');

        return $query
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('reference_number', 'like', "%{$term}%")
                ->orWhere('external_reference', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%")
                ->orWhere('sender', 'like', "%{$term}%")
                ->orWhere('recipient', 'like', "%{$term}%")))
            ->when(in_array($direction, Status::keys('correspondence_direction'), true), fn ($q) => $q->where('direction', $direction))
            ->when(in_array($status, Status::keys('correspondence'), true), fn ($q) => $q->where('status', $status))
            ->when($request->query('priority'), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', (int) $d))
            ->when($request->query('assigned_to'), fn ($q, $u) => $q->where('assigned_to', (int) $u))
            ->when($this->dateParam($request, 'from'), fn ($q, $d) => $q->whereDate('received_or_sent_date', '>=', $d))
            ->when($this->dateParam($request, 'to'), fn ($q, $d) => $q->whereDate('received_or_sent_date', '<=', $d))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->query('confidential') === 'yes', fn ($q) => $q->where('is_confidential', true))
            ->when($request->query('confidential') === 'no', fn ($q) => $q->where('is_confidential', false));
    }

    private function dateParam(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Correspondence $letter = null): array
    {
        $rules = [
            'external_reference' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'sender' => ['required', 'string', 'max:255'],
            'recipient' => ['required', 'string', 'max:255'],
            'letter_date' => ['nullable', 'date'],
            'received_or_sent_date' => ['nullable', 'date'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
            'responsible_officer_id' => ['nullable', Rule::exists('users', 'id')],
            'priority' => ['required', Rule::in(Status::keys('correspondence_priority'))],
            'is_confidential' => ['boolean'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];

        if ($letter === null) {
            // Direction, assignment and the scanned letter are set at registration;
            // afterwards they change only through the follow-up actions.
            $rules += [
                'direction' => ['required', Rule::in(Status::keys('correspondence_direction'))],
                'assigned_to' => ['nullable', 'integer', Rule::in(array_keys(self::assignableUsers()))],
                'response_due_date' => ['nullable', 'date'],
                'assignment_note' => ['nullable', 'string', 'max:2000'],
                'scan' => FileStore::rules(),
            ];
        }

        $data = $request->validate($rules);
        $data['is_confidential'] = $request->boolean('is_confidential');

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Correspondence $letter): array
    {
        return [
            'letter' => $letter,
            'departments' => Department::options(),
            'users' => self::assignableUsers(),
        ];
    }

    /**
     * Active users who can open correspondence (and so act on an assignment).
     *
     * @return array<int, string>
     */
    public static function assignableUsers(): array
    {
        return User::query()->active()
            ->where(fn (Builder $q) => $q
                ->whereHas('permissions', fn ($p) => $p->where('name', 'correspondence.view'))
                ->orWhereHas('roles', fn ($r) => $r->where('name', config('ndc.super_admin_role'))
                    ->orWhereHas('permissions', fn ($p) => $p->where('name', 'correspondence.view'))))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function notifyAssignee(Correspondence $letter, User $actor): void
    {
        $assignee = $letter->assignee()->first();
        if ($assignee === null || $assignee->is($actor)) {
            return;
        }

        $assignee->notify(new GenericNotification(
            'correspondence_assigned',
            'Letter assigned to you',
            'The letter :ref ":subject" has been assigned to you for action.',
            ['ref' => $letter->reference_number, 'subject' => $letter->subject],
            route('correspondence.show', $letter),
        ));
    }
}

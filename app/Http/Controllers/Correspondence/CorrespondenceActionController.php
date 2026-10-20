<?php

namespace App\Http\Controllers\Correspondence;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\Audit;
use App\Support\FileStore;
use App\Support\ReferenceNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Follow-up workflow for a letter. Every action adds an entry to the
 * letter's history (CorrespondenceAction) and to the audit trail.
 */
class CorrespondenceActionController extends Controller
{
    /**
     * Assign the letter to an officer for action or response.
     */
    public function assign(Request $request, Correspondence $correspondence): RedirectResponse
    {
        $this->authorize('assign', $correspondence);
        $this->ensureAllowed($correspondence, 'assign');

        $data = $request->validate([
            'assigned_to' => ['required', 'integer', Rule::in(array_keys(CorrespondenceController::assignableUsers()))],
            'response_due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($correspondence, $data, $request) {
            $correspondence->update([
                'assigned_to' => (int) $data['assigned_to'],
                'response_due_date' => $data['response_due_date'] ?? null,
            ]);
            $correspondence->recordAction('assigned', $request->user(), (int) $data['assigned_to'], $data['note'] ?? null, $data['response_due_date'] ?? null);
        });
        Audit::log('assigned', $correspondence, $correspondence->assignee?->name);

        $this->notify($correspondence, (int) $data['assigned_to'], $request->user(), 'correspondence_assigned',
            'Letter assigned to you', 'The letter :ref ":subject" has been assigned to you for action.');

        return back()->with('success', __('Letter assigned.'));
    }

    /**
     * Forward the letter to another officer, who becomes responsible for it.
     */
    public function forward(Request $request, Correspondence $correspondence): RedirectResponse
    {
        $this->authorize('handle', $correspondence);
        $this->ensureAllowed($correspondence, 'forward');

        $data = $request->validate([
            'to_user_id' => ['required', 'integer', Rule::in(array_keys(CorrespondenceController::assignableUsers())), Rule::notIn([$request->user()->id])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $to = (int) $data['to_user_id'];

        DB::transaction(function () use ($correspondence, $data, $request, $to) {
            $correspondence->update(['assigned_to' => $to, 'status' => 'forwarded']);
            $correspondence->recordAction('forwarded', $request->user(), $to, $data['note'] ?? null);
        });
        Audit::log('forwarded', $correspondence, User::query()->find($to)?->name);

        $this->notify($correspondence, $to, $request->user(), 'correspondence_forwarded',
            'Letter forwarded to you', ':name forwarded the letter :ref ":subject" to you.');

        return back()->with('success', __('Letter forwarded.'));
    }

    /**
     * Record the response to an incoming letter: link an existing outgoing
     * letter or register a new outgoing reply.
     */
    public function respond(Request $request, Correspondence $correspondence): RedirectResponse
    {
        $this->authorize('respond', $correspondence);
        $this->ensureAllowed($correspondence, 'respond');
        $user = $request->user();

        $data = $request->validate([
            'mode' => ['required', Rule::in(['new', 'link', 'none'])],
            'reply_id' => ['exclude_unless:mode,link', 'required', 'integer', Rule::exists('correspondences', 'id')
                ->where('direction', 'outgoing')->whereNull('deleted_at')],
            'reply_subject' => ['exclude_unless:mode,new', 'required', 'string', 'max:255'],
            'reply_recipient' => ['exclude_unless:mode,new', 'required', 'string', 'max:255'],
            'reply_date' => ['exclude_unless:mode,new', 'nullable', 'date'],
            'reply_summary' => ['exclude_unless:mode,new', 'nullable', 'string', 'max:10000'],
            'reply_sent' => ['exclude_unless:mode,new', 'boolean'],
            'reply_scan' => ['exclude_unless:mode,new', ...FileStore::rules()],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $reply = null;
        if ($data['mode'] === 'link') {
            $reply = Correspondence::query()->findOrFail($data['reply_id']);
            if (! $reply->isVisibleTo($user) || $reply->is($correspondence)) {
                return back()->withErrors(['reply_id' => __('The selected reply letter is not available.')]);
            }
        }

        $reply = DB::transaction(function () use ($correspondence, $data, $request, $user, $reply) {
            if ($data['mode'] === 'new') {
                $sent = $request->boolean('reply_sent');
                $reply = Correspondence::query()->create([
                    'reference_number' => ReferenceNumber::next('OUT'),
                    'direction' => 'outgoing',
                    'subject' => $data['reply_subject'],
                    'sender' => (string) setting('organisation_name', config('ndc.organisation')),
                    'recipient' => $data['reply_recipient'],
                    'letter_date' => $data['reply_date'] ?? today(),
                    'received_or_sent_date' => $sent ? ($data['reply_date'] ?? today()) : null,
                    'department_id' => $correspondence->department_id,
                    'responsible_officer_id' => $user->id,
                    'status' => $sent ? 'sent' : 'pending',
                    'priority' => $correspondence->priority,
                    'is_confidential' => $correspondence->is_confidential,
                    'summary' => $data['reply_summary'] ?? null,
                    'parent_id' => $correspondence->id,
                    'created_by' => $user->id,
                ]);
                $reply->recordAction('registered', $user, null, __('Reply to :ref', ['ref' => $correspondence->reference_number]));
                if ($sent) {
                    $reply->recordAction('sent', $user);
                }
                if ($request->hasFile('reply_scan')) {
                    FileStore::attach($reply, $request->file('reply_scan'), 'default', __('Scanned letter'));
                }
            } elseif ($reply !== null) {
                $reply->update(['parent_id' => $correspondence->id]);
            }

            $correspondence->forceFill(['status' => 'responded', 'responded_at' => now()])->save();

            $note = trim(($reply ? __('Reply: :ref', ['ref' => $reply->reference_number]).'. ' : '').($data['note'] ?? ''));
            $correspondence->recordAction('responded', $user, null, $note !== '' ? $note : null);

            return $reply;
        });
        Audit::log('responded', $correspondence, $reply?->reference_number);

        return back()->with('success', $reply
            ? __('Response recorded with reply :ref.', ['ref' => $reply->reference_number])
            : __('Response recorded.'));
    }

    /**
     * Mark an outgoing letter as dispatched.
     */
    public function markSent(Request $request, Correspondence $correspondence): RedirectResponse
    {
        $this->authorize('markSent', $correspondence);
        $this->ensureAllowed($correspondence, 'send');

        $data = $request->validate([
            'sent_date' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($correspondence, $data, $request) {
            $correspondence->update([
                'status' => 'sent',
                'received_or_sent_date' => $data['sent_date'] ?? $correspondence->received_or_sent_date ?? today(),
            ]);
            $correspondence->recordAction('sent', $request->user(), null, $data['note'] ?? null);
        });
        Audit::log('sent', $correspondence);

        return back()->with('success', __('Letter marked as sent.'));
    }

    public function close(Request $request, Correspondence $correspondence): RedirectResponse
    {
        $this->authorize('close', $correspondence);
        $this->ensureAllowed($correspondence, 'close');

        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($correspondence, $data, $request) {
            $correspondence->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
            $correspondence->recordAction('closed', $request->user(), null, $data['note'] ?? null);
        });
        Audit::log('closed', $correspondence, $data['note'] ?? null);

        return back()->with('success', __('Letter closed.'));
    }

    /**
     * Add a follow-up note to the history without changing the status.
     */
    public function note(Request $request, Correspondence $correspondence): RedirectResponse
    {
        $this->authorize('handle', $correspondence);
        $this->ensureAllowed($correspondence, 'note');

        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        $correspondence->recordAction('note', $request->user(), null, $data['note']);
        Audit::log('follow_up_noted', $correspondence);

        return back()->with('success', __('Follow-up note added.'));
    }

    /**
     * Workflow state rules apply to everyone, including Super Administrators
     * (who pass every policy check).
     */
    private function ensureAllowed(Correspondence $letter, string $action): void
    {
        abort_unless($letter->allowsAction($action), 403, __('This action is not available for the letter in its current state.'));
    }

    private function notify(Correspondence $letter, int $userId, User $actor, string $kind, string $title, string $body): void
    {
        $recipient = User::query()->active()->find($userId);
        if ($recipient === null || $recipient->is($actor)) {
            return;
        }

        $recipient->notify(new GenericNotification($kind, $title, $body, [
            'ref' => $letter->reference_number,
            'subject' => $letter->subject,
            'name' => $actor->name,
        ], route('correspondence.show', $letter)));
    }
}

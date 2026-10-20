<?php

namespace App\Reports;

use App\Models\Category;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Support\Status;
use Illuminate\Http\Request;

class DocumentRegisterReport extends Report
{
    public function key(): string
    {
        return 'documents';
    }

    public function title(): string
    {
        return __('Document register');
    }

    public function description(): string
    {
        return __('Registered documents with classification, status and key dates.');
    }

    public function permission(): string
    {
        return 'documents.view';
    }

    public function filters(): array
    {
        return [
            'category' => ['label' => 'Type', 'type' => 'select', 'options' => Category::options('document')],
            'department' => ['label' => 'Department', 'type' => 'select', 'options' => Department::options()],
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => Status::options('document')],
            'confidentiality' => ['label' => 'Confidentiality', 'type' => 'select', 'options' => Status::options('confidentiality')],
            ...$this->dateRange('Document date'),
        ];
    }

    public function columns(): array
    {
        return ['Reference', 'Title', 'Type', 'Department', 'Owner', 'Confidentiality', 'Status', 'Version', 'Document date', 'Review date', 'Expiry date'];
    }

    public function rows(Request $request, User $user): iterable
    {
        return Document::query()->visibleTo($user)->with(['category', 'department', 'owner'])
            ->when($request->query('category'), fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('confidentiality'), fn ($q, $c) => $q->where('confidentiality', $c))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('document_date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('document_date', '<=', $d))
            ->orderBy('reference_number')->get()
            ->map(fn (Document $d) => [
                $d->reference_number, $d->title, $d->category?->displayName(), $d->department?->displayName(), $d->owner?->name,
                Status::label('confidentiality', $d->confidentiality), Status::label('document', $d->status), $d->current_version,
                fmt_date($d->document_date), fmt_date($d->review_date), fmt_date($d->expiry_date),
            ]);
    }
}

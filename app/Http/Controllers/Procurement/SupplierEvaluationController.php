<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Supplier performance records (quality, delivery, communication; 1-5).
 */
class SupplierEvaluationController extends Controller
{
    public function store(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $rating = ['required', 'integer', 'between:1,5'];
        $data = $request->validate([
            'purchase_order_id' => ['nullable', Rule::exists('purchase_orders', 'id')->where('supplier_id', $supplier->id)],
            'quality_rating' => $rating,
            'delivery_rating' => $rating,
            'communication_rating' => $rating,
            'comments' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var SupplierEvaluation $evaluation */
        $evaluation = $supplier->evaluations()->create([...$data, 'evaluated_by' => $request->user()->id]);

        Audit::log('evaluated', $supplier, __('Score :score / 5', ['score' => number_format($evaluation->score(), 1)]));

        return back()->with('success', __('Performance evaluation recorded.'));
    }

    public function destroy(Supplier $supplier, SupplierEvaluation $evaluation): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $evaluation->delete();
        Audit::log('evaluation_removed', $supplier, __('Score :score / 5', ['score' => number_format($evaluation->score(), 1)]));

        return back()->with('success', __('Evaluation removed.'));
    }
}

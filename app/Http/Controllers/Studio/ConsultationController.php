<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Lịch hẹn gọi tư vấn của studio: sắp tới (theo ngày), đã qua; đánh dấu đã gọi / huỷ. */
class ConsultationController extends Controller
{
    use ResolvesStudio;

    public function index(Request $request): View
    {
        $studio = $this->studio($request);
        $upcoming = $studio->consultations()->with('client')->where('status', 'booked')
            ->where('starts_at', '>=', now()->subHours(3))->orderBy('starts_at')->get();
        $past = $studio->consultations()->with('client')
            ->where(fn ($q) => $q->where('status', '!=', 'booked')->orWhere('starts_at', '<', now()->subHours(3)))
            ->where('starts_at', '>=', now()->subDays(60))->orderByDesc('starts_at')->limit(50)->get();

        return view('consultations.index', compact('studio', 'upcoming', 'past'));
    }

    public function update(Request $request, int $consultation): RedirectResponse
    {
        $studio = $this->studio($request);
        $c = $studio->consultations()->findOrFail($consultation);
        $data = $request->validate(['status' => ['required', Rule::in(['booked', 'done', 'cancelled'])]]);
        $c->forceFill(['status' => $data['status']])->save();

        return back()->with('status', $c->name.' · '.$c->whenLabel().': '.mb_strtolower($c->statusLabel()).'.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePeriodRequest;
use App\Http\Requests\Admin\UpdatePeriodRequest;
use App\Models\AcademicSession;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PeriodController extends Controller
{
    /**
     * List the periods of one session, in timetable order.
     *
     * The session filter defaults to the active session, which is the one an
     * admin is configuring 99% of the time.
     */
    public function index(Request $request): Response
    {
        $sessions = $this->sessionOptions();
        $selectedSessionId = $this->selectedSessionId($request, $sessions);

        $periods = Period::forSession($selectedSessionId)
            ->map(fn (Period $period) => [
                'id' => $period->id,
                'number' => $period->number,
                'label' => $period->label,
                'start_time' => $period->start_time->format('H:i'),
                'end_time' => $period->end_time->format('H:i'),
                'is_break' => $period->is_break,
            ]);

        return Inertia::render('Admin/Periods/Index', [
            'periods' => $periods,
            'sessions' => $sessions,
            'selectedSessionId' => $selectedSessionId,
        ]);
    }

    /**
     * Show the create form.
     *
     * A session_id query parameter preselects the session, which is how the
     * "New Period" button on a filtered index page keeps its context.
     */
    public function create(Request $request): Response
    {
        $sessions = $this->sessionOptions();
        $nextNumber = Period::forSession($this->selectedSessionId($request, $sessions))
            ->max('number');

        return Inertia::render('Admin/Periods/Create', [
            'sessions' => $sessions,
            'selectedSessionId' => $this->selectedSessionId($request, $sessions),
            // The next free grid row, so the common case needs no typing.
            'suggestedNumber' => $nextNumber === null ? 1 : $nextNumber + 1,
        ]);
    }

    /**
     * Store a newly created period.
     */
    public function store(StorePeriodRequest $request): RedirectResponse
    {
        $period = Period::create($request->validated());

        return redirect()
            ->route('admin.periods.index', [
                'session_id' => $period->academic_session_id,
            ])
            ->with('success', 'Period created successfully.');
    }

    /**
     * Show the edit form.
     */
    public function edit(Period $period): Response
    {
        return Inertia::render('Admin/Periods/Edit', [
            'period' => [
                'id' => $period->id,
                'academic_session_id' => $period->academic_session_id,
                'number' => $period->number,
                'label' => $period->label,
                'start_time' => $period->start_time->format('H:i'),
                'end_time' => $period->end_time->format('H:i'),
                'is_break' => $period->is_break,
            ],
            'sessions' => $this->sessionOptions(),
        ]);
    }

    /**
     * Update an existing period.
     */
    public function update(UpdatePeriodRequest $request, Period $period): RedirectResponse
    {
        $period->update($request->validated());

        return redirect()
            ->route('admin.periods.index', [
                'session_id' => $period->academic_session_id,
            ])
            ->with('success', 'Period updated successfully.');
    }

    /**
     * Soft delete a period.
     *
     * Timetable slots do not exist yet, so there is nothing to block on. When
     * they do, this is where the "period is still scheduled" check belongs -
     * the same shape as the teachers module refusing to delete a teacher who
     * still holds assignments.
     */
    public function destroy(Period $period): RedirectResponse
    {
        $sessionId = $period->academic_session_id;

        $period->delete();

        return redirect()
            ->route('admin.periods.index', ['session_id' => $sessionId])
            ->with('success', 'Period deleted.');
    }

    /**
     * Sessions for the drop-downs, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function sessionOptions(): Collection
    {
        return AcademicSession::query()
            ->orderByDesc('name')
            ->get()
            ->map(fn (AcademicSession $session) => [
                'id' => $session->id,
                'name' => $session->name,
                'is_active' => $session->is_active,
            ]);
    }

    /**
     * The session the page should show.
     *
     * An unknown or missing session_id falls back to the active one, and then
     * to the first available, so the page always has something to render.
     *
     * @param  Collection<int, array<string, mixed>>  $sessions
     */
    private function selectedSessionId(Request $request, Collection $sessions): int
    {
        $requested = $request->query('session_id');

        if (is_numeric($requested) && $sessions->contains('id', (int) $requested)) {
            return (int) $requested;
        }

        $active = $sessions->firstWhere('is_active', true);

        return (int) ($active['id'] ?? $sessions->first()['id'] ?? 0);
    }
}

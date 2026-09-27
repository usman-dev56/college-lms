<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAcademicSessionRequest;
use App\Http\Requests\Admin\UpdateAcademicSessionRequest;
use App\Models\AcademicSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AcademicSessionController extends Controller
{
    /**
     * List all academic sessions (active first, then most recent).
     */
    public function index(): Response
    {
        $sessions = AcademicSession::query()
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (AcademicSession $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'start_date' => $s->start_date->toDateString(),
                'end_date' => $s->end_date->toDateString(),
                'is_active' => $s->is_active,
            ]);

        return Inertia::render('Admin/AcademicSessions/Index', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/AcademicSessions/Create');
    }

    /**
     * Store a newly created session.
     */
    public function store(StoreAcademicSessionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // If the new session is marked active, deactivate all others first.
        // This runs in a transaction inside the model helper.
        if (! empty($data['is_active'])) {
            // Create the session as inactive first, then activate it
            // so the "single active session" rule is enforced.
            $data['is_active'] = false;
            $session = AcademicSession::create($data);
            $session->activate();
        } else {
            AcademicSession::create($data);
        }

        return redirect()
            ->route('admin.academic-sessions.index')
            ->with('success', 'Academic session created successfully.');
    }

    /**
     * Show the edit form.
     */
    public function edit(AcademicSession $academicSession): Response
    {
        return Inertia::render('Admin/AcademicSessions/Edit', [
            'session' => [
                'id' => $academicSession->id,
                'name' => $academicSession->name,
                'start_date' => $academicSession->start_date->toDateString(),
                'end_date' => $academicSession->end_date->toDateString(),
                'is_active' => $academicSession->is_active,
            ],
        ]);
    }

    /**
     * Update an existing session.
     */
    public function update(
        UpdateAcademicSessionRequest $request,
        AcademicSession $academicSession
    ): RedirectResponse {
        $data = $request->validated();

        $wantsActive = ! empty($data['is_active']);
        $data['is_active'] = false; // we set it via activate() if needed

        $academicSession->update($data);

        if ($wantsActive && ! $academicSession->is_active) {
            $academicSession->activate();
        } elseif (! $wantsActive && $academicSession->is_active) {
            // Admin is explicitly deactivating the currently active session.
            // This is allowed but leaves the system without an active session.
            $academicSession->update(['is_active' => false]);
        }

        return redirect()
            ->route('admin.academic-sessions.index')
            ->with('success', 'Academic session updated successfully.');
    }

    /**
     * Delete a session.
     *
     * Prevented if the session is currently active, to avoid orphaning
     * classes and records.
     */
    public function destroy(AcademicSession $academicSession): RedirectResponse
    {
        if ($academicSession->is_active) {
            return redirect()
                ->route('admin.academic-sessions.index')
                ->with('error', 'Cannot delete the active session. Activate another session first.');
        }

        $academicSession->delete();

        return redirect()
            ->route('admin.academic-sessions.index')
            ->with('success', 'Academic session deleted.');
    }

    /**
     * Activate a session and deactivate all others.
     */
    public function activate(AcademicSession $academicSession): RedirectResponse
    {
        $academicSession->activate();

        return redirect()
            ->route('admin.academic-sessions.index')
            ->with('success', "Session {$academicSession->name} is now active.");
    }
}
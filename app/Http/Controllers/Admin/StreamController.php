<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStreamRequest;
use App\Http\Requests\Admin\UpdateStreamRequest;
use App\Models\Stream;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StreamController extends Controller
{
    /**
     * List all streams (active first, then alphabetical by name).
     */
    public function index(): Response
    {
        $streams = Stream::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Stream $stream) => [
                'id' => $stream->id,
                'name' => $stream->name,
                'code' => $stream->code,
                'description' => $stream->description,
                'is_active' => $stream->is_active,
            ]);

        return Inertia::render('Admin/Streams/Index', [
            'streams' => $streams,
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Streams/Create');
    }

    /**
     * Store a newly created stream.
     */
    public function store(StoreStreamRequest $request): RedirectResponse
    {
        Stream::create($request->validated());

        return redirect()
            ->route('admin.streams.index')
            ->with('success', 'Stream created successfully.');
    }

    /**
     * Show the edit form.
     */
    public function edit(Stream $stream): Response
    {
        return Inertia::render('Admin/Streams/Edit', [
            'stream' => [
                'id' => $stream->id,
                'name' => $stream->name,
                'code' => $stream->code,
                'description' => $stream->description,
                'is_active' => $stream->is_active,
            ],
        ]);
    }

    /**
     * Update an existing stream.
     */
    public function update(
        UpdateStreamRequest $request,
        Stream $stream
    ): RedirectResponse {
        $stream->update($request->validated());

        return redirect()
            ->route('admin.streams.index')
            ->with('success', 'Stream updated successfully.');
    }

    /**
     * Delete a stream.
     *
     * Streams are soft deleted, so the code and name are freed up for
     * future use (the unique indexes are partial on deleted_at IS NULL).
     * Streams have no single-active rule and are deactivated with a
     * normal edit rather than a dedicated action.
     *
     * Dependent checks belong here once subjects and enrollments
     * reference streams (sub-stages 2.3+).
     */
    public function destroy(Stream $stream): RedirectResponse
    {
        $stream->delete();

        return redirect()
            ->route('admin.streams.index')
            ->with('success', 'Stream deleted.');
    }
}

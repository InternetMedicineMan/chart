<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\LocalDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IdeaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Note::create($request->validate(['body' => ['required', 'string', 'max:20000']]) + ['user_id' => $request->user()->id, 'kind' => 'thought']);

        return back()->with('message', 'Idea saved.');
    }

    public function update(Request $request, int $idea): RedirectResponse
    {
        Note::forUser($request->user())->where('kind', 'thought')->findOrFail($idea)
            ->update($request->validate(['body' => ['required', 'string', 'max:20000']]) + ['needs_review' => false]);

        return back()->with('message', 'Idea updated.');
    }

    public function review(Request $request, int $idea, LocalDate $dates): RedirectResponse
    {
        $record = Note::forUser($request->user())->where('kind', 'thought')->whereNull('archived_at')->findOrFail($idea);
        if ($record->needs_review || ! $record->reviewed_at || $dates->date($record->reviewed_at, $dates->timezone($request->user())) !== $dates->today($request->user())) {
            $record->update(['reviewed_at' => now(), 'needs_review' => false]);
        }

        return back()->with('message', 'Idea reviewed today.');
    }

    public function archive(Request $request, int $idea): RedirectResponse
    {
        $record = Note::forUser($request->user())->where('kind', 'thought')->findOrFail($idea);
        if (! $record->archived_at) {
            $record->update(['archived_at' => now()]);
        }

        return back()->with('message', 'Idea archived. You can restore it from Archived.');
    }

    public function restore(Request $request, int $idea): RedirectResponse
    {
        Note::forUser($request->user())->where('kind', 'thought')->findOrFail($idea)->update(['archived_at' => null]);

        return back()->with('message', 'Idea restored.');
    }
}

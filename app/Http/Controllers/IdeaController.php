<?php

namespace App\Http\Controllers;

use App\Models\Note;
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
            ->update($request->validate(['body' => ['required', 'string', 'max:20000']]));

        return back()->with('message', 'Idea updated.');
    }

    public function review(Request $request, int $idea): RedirectResponse
    {
        Note::forUser($request->user())->where('kind', 'thought')->findOrFail($idea)->update(['reviewed_at' => now()]);

        return back()->with('message', 'Marked reviewed.');
    }
}

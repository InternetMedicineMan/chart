<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoadmapRequest;
use App\Models\Roadmap;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoadmapController extends Controller
{
    public function index(): Response
    {
        $roadmaps = Roadmap::with('user')
            ->orderByDesc('votes_count')
            ->get()
            ->map(function ($roadmap) {
                $roadmap->has_voted = auth()->check() ? $roadmap->hasVotedBy(auth()->user()) : false;

                return $roadmap;
            });

        return Inertia::render('Roadmap/Index', [
            'roadmaps' => $roadmaps,
            'seo' => [
                'title' => __('Roadmap'),
                'description' => __('View our product roadmap and request new features'),
            ],
        ]);
    }

    public function store(StoreRoadmapRequest $request): RedirectResponse
    {
        Roadmap::create([
            'user_id' => auth()->id(),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'status' => 'pending',
        ]);

        return redirect()->route('roadmap.index')
            ->with('success', 'Feature request submitted successfully!');
    }

    public function vote(Roadmap $roadmap): RedirectResponse
    {
        $user = auth()->user();

        if ($roadmap->hasVotedBy($user)) {
            $roadmap->votes()->where('user_id', $user->id)->delete();
            $roadmap->decrement('votes_count');
        } else {
            $roadmap->votes()->create(['user_id' => $user->id]);
            $roadmap->increment('votes_count');
        }

        return redirect()->back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Changelog;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ChangelogController extends Controller
{
    public function index()
    {
        $changelogs = Changelog::latest('published_at')->get();

        $changelogs = $changelogs->map(function ($changelog) {
            $changelog->description = Str::markdown($changelog->description);

            return $changelog;
        });

        return Inertia::render('Changelog', [
            'changelogs' => $changelogs,
            'seo' => [
                'title' => __('My Awesome App Changelog'),
                'description' => 'See what\'s new in Larafast Starter Kits: TALL, VILT, API and Directories',
                'canonical' => route('changelog'),
                'image' => route('og-image', [
                    'title' => 'Larafast Changelog',
                    'description' => "See what's new in Larafast Starter Kits: TALL, VILT, API and Directories",
                ]),
            ],
        ]);
    }
}

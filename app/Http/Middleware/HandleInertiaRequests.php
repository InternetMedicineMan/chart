<?php

namespace App\Http\Middleware;

use App\Models\FeedNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'message' => fn () => $request->session()->get('message'),
            'unreadNotifications' => fn () => $request->user()?->two_factor_confirmed_at
                && (int) $request->user()->id === (int) config('chart.owner_id')
                ? FeedNotification::forUser($request->user())->where('status', 'unread')->count() : 0,
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}

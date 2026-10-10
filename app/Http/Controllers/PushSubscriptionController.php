<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePushSubscriptionRequest;
use App\Models\FeedNotification;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\BrowserPush;
use App\Services\ReminderDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PushSubscriptionController extends Controller
{
    public function index(Request $request, BrowserPush $push): Response
    {
        return Inertia::render('Work/PushSettings', ['configured' => $push->configured(), 'publicKey' => config('chart.push.public_key'),
            'subscriptions' => PushSubscription::forUser($request->user())->get(['id', 'label', 'created_at'])]);
    }

    public function store(SavePushSubscriptionRequest $request, BrowserPush $push): JsonResponse
    {
        abort_unless($push->configured(), 503, 'Configure Web Push before enabling this browser.');
        $data = $request->validated();
        $subscription = DB::transaction(function () use ($request, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', $data['endpoint']);
            $existing = PushSubscription::where('endpoint_hash', $hash)->first();
            abort_if($existing && $existing->user_id !== $request->user()->id, 409);
            abort_if(! $existing && PushSubscription::forUser($request->user())->count() >= 10, 422, 'Remove an old device before adding another.');

            return PushSubscription::updateOrCreate(['endpoint_hash' => $hash], ['user_id' => $request->user()->id, 'label' => $data['label'], 'subscription' => ['endpoint' => $data['endpoint'], 'keys' => $data['keys']]]);
        });
        $request->session()->put('chart_push_subscription', $subscription->id);

        return response()->json(['id' => $subscription->id]);
    }

    public function destroy(Request $request, int $subscription): RedirectResponse
    {
        DB::transaction(function () use ($request, $subscription) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            PushSubscription::forUser($request->user())->findOrFail($subscription)->delete();
        });

        return back()->with('message', 'Notifications disabled for that device.');
    }

    public function test(Request $request, int $subscription, ReminderDelivery $reminders): RedirectResponse
    {
        $record = PushSubscription::forUser($request->user())->findOrFail($subscription);
        DB::transaction(function () use ($request, $record) {
            $notification = FeedNotification::create(['user_id' => $request->user()->id, 'dedup_key' => 'push-test:'.Str::uuid(), 'type' => 'push_test', 'title' => 'Test notification', 'body' => 'Your browser notification test was queued.']);
            PushDelivery::create(['user_id' => $request->user()->id, 'notification_id' => $notification->id, 'push_subscription_id' => $record->id, 'available_at' => now()]);
        });
        $reminders->dispatch($request->user());

        return back()->with('message', 'Test notification queued. Delivery requires the notification worker.');
    }
}

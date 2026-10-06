<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Checkout;
use Symfony\Component\HttpFoundation\Response;

class StripeController extends Controller
{
    public function subscriptionCheckout(Request $request, $price)
    {
        $user = $request->user();

        if ($user->subscribedToPrice($price)) {
            return redirect()->back()->dangerBanner(__('You are already subscribed to that plan'));
        }

        if ($user->subscribed() && $user->subscription()?->valid()) {
            $user->subscription()
                ?->load('owner')
                ->skipTrial()
                ->swap($price);

            // Replace back() with the route where user should be redirected after successful subscription
            return redirect()->back()->banner(__('You have successfully subscribed to :name plan', ['name' => $price]));
        }

        $checkout = $user
            ->newSubscription('default', $price);

        // If user already used his trial with different plan, new trial will not be allowed for him
        if (! $user->trialIsUsed()) {
            $checkout = $checkout->trialDays(config('services.stripe.trial_period_days'));
        }

        return $checkout
            ->allowPromotionCodes() // Remove this if you do not allow promo codes
            ->checkout([
                'success_url' => route('stripe.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('dashboard'),
            ]);
    }

    public function productCheckout(Request $request, $price): Checkout
    {
        return $request->user()->checkout($price, [
            'success_url' => route('stripe.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('dashboard'),
            'metadata' => [
                'price' => $price,
            ],
        ]);
    }

    public function success(Request $request): RedirectResponse
    {
        $sessionId = $request->get('session_id');

        try {
            Cashier::stripe()->checkout->sessions->retrieve($sessionId);
        } catch (Exception $exception) {
            return redirect()->route('dashboard')->dangerBanner('Something went wrong');
        }

        $request->user()->update(['trial_is_used' => true]);

        return redirect()->route('dashboard')->banner('You have successfully subscribed');
    }

    public function error(): RedirectResponse
    {
        return redirect()->route('stripe.plans')->dangerBanner('Something Went Wrong');
    }

    public function billing(Request $request): Response
    {
        $url = $request->user()->billingPortalUrl(route('dashboard'));

        return Inertia::location($url);
    }
}

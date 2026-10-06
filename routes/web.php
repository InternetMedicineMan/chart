<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Invoices\DownloadInvoiceController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\Payments\LemonSqueezyController;
use App\Http\Controllers\Payments\PaddleController;
use App\Http\Controllers\Payments\StripeController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\Subscribed;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [HomeController::class, 'index']);
Route::get('sitemap', [SitemapController::class, 'index'])->name('sitemap');
Route::get('changelog', [ChangelogController::class, 'index'])->name('changelog');

// Demo Coming Soon Page
Route::get('coming-soon', function () {
    return Inertia::render('ComingSoon');
})->name('coming-soon');

Route::prefix('auth')->group(function () {
    Route::get('/redirect/{driver}', [SocialiteController::class, 'redirect'])
        ->name('socialite.redirect');
    Route::get('/callback/{driver}', [SocialiteController::class, 'callback'])
        ->name('socialite.callback');

    // Magic Links
    Route::post('/magic-link', [MagicLinkController::class, 'sendMagicLink'])->name('magic.link');
    Route::get('/magic-link/{token}', [MagicLinkController::class, 'loginWithMagicLink'])->name('magic.link.login');
});

Route::post('coming-soon', [\App\Http\Controllers\ComingSoonController::class, 'index'])->name('coming-soon.store');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{article:slug}', [BlogController::class, 'article'])->name('blog.article');

Route::get('/roadmap', [RoadmapController::class, 'index'])->name('roadmap.index');

Route::get('og-image/{title?}/{description?}', OgImageController::class)->name('og-image');

// For testing and modifying the default image template
Route::get('og-image-testing', function () {
    return view('seo.image', [
        'title' => 'Your dynamic og image',
        'description' => 'Your dynamic og image description', // optional
    ]);
});

// Paddle Checkout Price endpoint is outside of auth middleware
// If you want to use it for authenticated users, move it inside the middleware
// Instructions for authenticated users are in the comments of the PaddleController.php file
Route::get('paddle/checkout/{price}', [PaddleController::class, 'checkout'])->name('paddle.checkout');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Here goes your auth user endpoints
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
    Route::get('/invoices/{invoice}/download', DownloadInvoiceController::class)->name('invoices.download');

    // Roadmap authenticated routes
    Route::post('/roadmap', [RoadmapController::class, 'store'])->name('roadmap.store');
    Route::post('/roadmap/{roadmap}/vote', [RoadmapController::class, 'vote'])->name('roadmap.vote');

    Route::prefix('stripe')->name('stripe.')->group(function () {
        Route::get('subscription-checkout/{price}', [StripeController::class, 'subscriptionCheckout'])->name('subscription.checkout');
        // If your product checkout does not require auth user,
        // move this part outside "auth:sanctum" middleware and change the logic inside method
        Route::get('product-checkout/{price}', [StripeController::class, 'productCheckout'])->name('product.checkout');
        Route::get('success', [StripeController::class, 'success'])->name('success');
        Route::get('error', [StripeController::class, 'error'])->name('error');
        Route::get('billing', [StripeController::class, 'billing'])->name('billing'); // Redirects to Customer Portal
    });

    Route::prefix('lemonsqueezy')->name('lemonsqueezy.')->group(function () {
        Route::get('subscription-checkout/{productId}/{variantId}', [LemonSqueezyController::class, 'subscriptionCheckout'])->name('subscription.checkout');
        // If your product checkout does not require auth user,
        // move this part outside "auth:sanctum" middleware and change the logic inside method
        Route::get('product-checkout/{variantId}', [LemonSqueezyController::class, 'productCheckout'])->name('product.checkout');
        Route::get('billing', [LemonSqueezyController::class, 'billing'])->name('billing'); // Redirects to Customer Portal
    });

    // Paddle Routes
    // Paddle Plan Checkouts can be found in resources/js/Components/Paddle/PaddlePlans.vue component

    Route::prefix('paddle')->name('paddle.')->group(function () {
        Route::get('/subscription/{price}/swap', [PaddleController::class, 'subscriptionSwap'])
            ->name('subscription.swap');
        Route::get('/subscription/cancel', [PaddleController::class, 'subscriptionCancel'])
            ->name('subscription.cancel');
    });

    Route::middleware([Subscribed::class])->group(function () {
        // Add endpoints that are only for subscribed users
    });
});

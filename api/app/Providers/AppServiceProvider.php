<?php

namespace App\Providers;

use App\Auth\TenantEloquentUserProvider;
use App\Models\User;
use App\Services\FakePaymentGateway;
use App\Services\PaymentGateway;
use App\Services\StripePaymentGateway;
use App\Support\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(PaymentGateway::class, function ($app): PaymentGateway {
            if ($app->runningUnitTests()) {
                return new FakePaymentGateway;
            }

            return $app->make(StripePaymentGateway::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('tenant-eloquent', function ($app, array $config): TenantEloquentUserProvider {
            return new TenantEloquentUserProvider($app['hash'], $config['model']);
        });

        JsonResource::withoutWrapping();

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->input('email'));
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->input('email'));
        });

        VerifyEmail::createUrlUsing(function (User $user): string {
            return URL::temporarySignedRoute(
                'verification.verify',
                now()->addHour(),
                [
                    'id' => $user->public_id,
                    'hash' => sha1($user->getEmailForVerification()),
                ],
            );
        });

        ResetPassword::createUrlUsing(function (object $user, string $token): string {
            return config('app.frontend_url').'/reset-password?token='.$token.'&email='.urlencode($user->email);
        });

        RateLimiter::for('booking', function (Request $request): Limit {
            $context = app(TenantContext::class);
            $tenantId = $context->has() ? $context->id() : 'none';

            return Limit::perMinute(10)->by($request->ip().'|'.$tenantId);
        });
    }
}

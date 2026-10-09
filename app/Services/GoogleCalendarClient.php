<?php

namespace App\Services;

use App\Exceptions\CalendarFailure;
use App\Models\CalendarConnection;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GoogleCalendarClient
{
    public const SCOPES = ['openid', 'email', 'https://www.googleapis.com/auth/calendar.calendarlist.readonly', 'https://www.googleapis.com/auth/calendar.events'];

    public function configured(): bool
    {
        return filled(config('chart.calendar.client_id')) && filled(config('chart.calendar.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('chart.calendar.redirect_uri') ?: route('calendar.callback');
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('chart.calendar.client_id'), 'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code', 'scope' => implode(' ', self::SCOPES), 'state' => $state,
            'access_type' => 'offline', 'prompt' => 'consent select_account', 'include_granted_scopes' => 'true',
        ]);
    }

    public function exchange(string $code): array
    {
        return $this->token(['code' => $code, 'redirect_uri' => $this->redirectUri(), 'grant_type' => 'authorization_code']);
    }

    private function token(array $fields): array
    {
        try {
            $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(20)->withOptions(['allow_redirects' => false])
                ->post('https://oauth2.googleapis.com/token', $fields + ['client_id' => config('chart.calendar.client_id'), 'client_secret' => config('chart.calendar.client_secret')]);
        } catch (ConnectionException) {
            throw new CalendarFailure;
        }
        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw new CalendarFailure(in_array($response->status(), [400, 401], true) ? 401 : $response->status());
        }

        return $response->json();
    }

    public function identity(string $token): array
    {
        try {
            $response = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(20)->withOptions(['allow_redirects' => false])->get('https://openidconnect.googleapis.com/v1/userinfo');
        } catch (ConnectionException) {
            throw new CalendarFailure;
        }
        if (! $response->successful() || ! is_string($response->json('sub')) || ! is_string($response->json('email')) || ! $response->json('email_verified')) {
            throw new CalendarFailure(401);
        }

        return $response->json();
    }

    private function accessToken(CalendarConnection $connection, bool $force = false): string
    {
        return DB::transaction(function () use ($connection, $force) {
            User::whereKey($connection->user_id)->lockForUpdate()->firstOrFail();
            $record = CalendarConnection::forUser($connection->user_id)->lockForUpdate()->findOrFail($connection->id);
            if ($record->status !== 'connected' || ! $record->refresh_token) {
                throw new CalendarFailure(401);
            }
            if (! $force && $record->access_token && $record->expires_at?->gt(now()->addMinute())) {
                return $record->access_token;
            }
            $tokens = $this->token(['refresh_token' => $record->refresh_token, 'grant_type' => 'refresh_token']);
            $record->update(['access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token'] ?? $record->refresh_token, 'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600))]);

            return $tokens['access_token'];
        });
    }

    public function request(CalendarConnection $connection, string $method, string $path, array $parameters = [], ?string $etag = null): array
    {
        try {
            $response = $this->send($this->accessToken($connection), $method, $path, $parameters, $etag);
            if ($response->status() === 401) {
                $response = $this->send($this->accessToken($connection, true), $method, $path, $parameters, $etag);
            }
            if (! $response->successful()) {
                throw new CalendarFailure($response->status());
            }

            $data = $response->json() ?? [];
            if (! is_array($data)) {
                throw new CalendarFailure;
            }

            return $data;
        } catch (ConnectionException) {
            throw new CalendarFailure;
        } catch (CalendarFailure $exception) {
            if ($exception->httpStatus === 401) {
                CalendarConnection::forUser($connection->user_id)->whereKey($connection->id)->where('revision', $connection->revision)->where('status', 'connected')->update(['status' => 'reauth_required', 'error' => $exception->getMessage()]);
            }
            throw $exception;
        }
    }

    private function send(string $token, string $method, string $path, array $parameters, ?string $etag): Response
    {
        $request = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(20)->withOptions(['allow_redirects' => false]);
        if ($etag) {
            $request = $request->withHeaders(['If-Match' => $etag]);
        }

        return $request->send($method, 'https://www.googleapis.com/calendar/v3/'.$path, [$method === 'GET' ? 'query' : 'json' => $parameters]);
    }

    public function pages(CalendarConnection $connection, string $path, array $parameters): array
    {
        $items = [];
        $page = null;
        $seen = [];
        do {
            $response = $this->request($connection, 'GET', $path, $parameters + ($page ? ['pageToken' => $page] : []));
            if (! is_array($response['items'] ?? [])) {
                throw new CalendarFailure;
            }
            array_push($items, ...($response['items'] ?? []));
            $page = $response['nextPageToken'] ?? null;
            if ($page && (isset($seen[$page]) || count($seen) >= 100)) {
                throw new CalendarFailure;
            }
            if ($page) {
                $seen[$page] = true;
            }
        } while ($page);

        return ['items' => $items, 'sync_token' => $response['nextSyncToken'] ?? null];
    }
}

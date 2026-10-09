<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDomainRequest;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DomainController extends Controller
{
    public function store(SaveDomainRequest $request): RedirectResponse
    {
        Domain::create($request->validated() + ['user_id' => $request->user()->id, 'slug' => Str::uuid()->toString()]);

        return back()->with('message', 'Domain created.');
    }

    public function update(SaveDomainRequest $request, int $domain): RedirectResponse
    {
        $record = Domain::forUser($request->user())->findOrFail($domain);
        abort_if($record->is_inbox, 422, 'The system Inbox cannot be changed.');
        $record->update($request->validated());

        return back()->with('message', 'Domain updated.');
    }

    public function destroy(Request $request, int $domain): RedirectResponse
    {
        DB::transaction(function () use ($request, $domain) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            Domain::forUser($request->user())->lockForUpdate()->findOrFail($domain)->delete();
        });

        return back()->with('message', 'Empty domain removed.');
    }
}

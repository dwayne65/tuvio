<?php

namespace App\Http\Middleware;

use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $currentOrg = $user ? CurrentOrganization::resolve($request) : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_path' => $user->avatar_path,
                    'is_admin' => (bool) $user->is_admin,
                    'email_verified' => $user->hasVerifiedEmail(),
                ] : null,
                'organizations' => $user ? $user->organizations()->get()->map(fn ($o) => [
                    'id' => $o->id, 'name' => $o->name, 'slug' => $o->slug,
                    'role' => $o->pivot->role,
                ]) : [],
                'currentOrganization' => $currentOrg ? [
                    'id' => $currentOrg->id, 'name' => $currentOrg->name, 'slug' => $currentOrg->slug,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app' => [
                'name' => config('app.name'),
                'currency' => config('ticketflow.default_currency'),
                'demo_mode' => config('ticketflow.demo_mode'),
            ],
            'notifications' => [
                'unread' => fn () => $user ? $user->unreadNotifications()->count() : 0,
            ],
        ];
    }
}

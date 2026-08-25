<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\VendorSubmission;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => ['success' => fn () => $request->session()->get('success')],
            'notificationCounts' => function () use ($request) {
                $user = $request->user();
                if (! $user || ! in_array($user->role, [UserRole::DEVELOPER, UserRole::COORDINATOR], true) || ! $user->hasPermission('coordinator.vendor')) return [];
                return VendorSubmission::where('site', $user->site)->whereNull('coordinator_opened_at')
                    ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
            },
        ];
    }
}

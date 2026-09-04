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
                if (! $user) {
                    return [];
                }

                if ($user->role === UserRole::VENDOR) {
                    $owned = VendorSubmission::where('created_by', $user->id)->where('site', $user->site);

                    return [
                        'vendor_revisions' => (clone $owned)->whereNotNull('resubmission_requested_at')->count(),
                        'released_ijin_lintas' => $request->routeIs('vendor.planning-lintas')
                            ? 0
                            : (clone $owned)->where('type', 'IJIN_LINTAS')->where('status', 'RELEASED')->whereNull('vendor_release_opened_at')->count(),
                    ];
                }

                if ($user->role === UserRole::PURCHASING_ADMIN) {
                    return ['IJIN_LINTAS' => VendorSubmission::where('site', $user->site)->where('type', 'IJIN_LINTAS')->whereNull('coordinator_opened_at')->count()];
                }

                if (! in_array($user->role, [UserRole::DEVELOPER, UserRole::COORDINATOR], true) || ! $user->hasPermission('coordinator.vendor')) {
                    return [];
                }

                return VendorSubmission::where('site', $user->site)->whereNull('coordinator_opened_at')
                    ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
            },
        ];
    }
}

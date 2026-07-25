<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformAppealAction;
use App\Enums\PlatformAppealStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ModeratePlatformAppealRequest;
use App\Models\PlatformAppeal;
use App\Models\User;
use App\Platform\ManagePlatformAppeals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlatformAppealController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'approved', 'denied', 'all'])],
        ]);
        $filter = (string) ($validated['status'] ?? 'active');
        $activeStatuses = [
            PlatformAppealStatus::Open->value,
            PlatformAppealStatus::Reviewing->value,
        ];
        $appeals = PlatformAppeal::query()
            ->when($filter === 'active', fn (Builder $appeals) => $appeals
                ->whereIn('status', $activeStatuses))
            ->when($filter === 'approved', fn (Builder $appeals) => $appeals
                ->where('status', PlatformAppealStatus::Approved))
            ->when($filter === 'denied', fn (Builder $appeals) => $appeals
                ->where('status', PlatformAppealStatus::Denied))
            ->with([
                'user:id,name,handle,email,suspended_at,suspension_reason',
                'reviewer:id,name',
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PlatformAppeal $appeal): array => [
                'id' => $appeal->getKey(),
                'actionUrl' => route('admin.appeals.update', $appeal),
                'status' => $appeal->status->value,
                'statusLabel' => $appeal->status->label(),
                'statement' => $appeal->statement,
                'decisionMessage' => $appeal->decision_message,
                'submittedAt' => $appeal->created_at->toIso8601String(),
                'reviewedAt' => $appeal->reviewed_at?->toIso8601String(),
                'reviewerName' => $appeal->reviewer?->name,
                'member' => [
                    'name' => $appeal->user->name,
                    'handle' => $appeal->user->handle,
                    'email' => $appeal->user->email,
                    'restricted' => $appeal->user->isSuspended(),
                    'restrictedAt' => $appeal->user->suspended_at?->toIso8601String(),
                    'internalReason' => $appeal->user->suspension_reason,
                ],
            ]);

        $counts = PlatformAppeal::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('admin/appeals', [
            'filter' => $filter,
            'counts' => [
                'active' => (int) $counts->get(PlatformAppealStatus::Open->value, 0)
                    + (int) $counts->get(PlatformAppealStatus::Reviewing->value, 0),
                'approved' => (int) $counts->get(PlatformAppealStatus::Approved->value, 0),
                'denied' => (int) $counts->get(PlatformAppealStatus::Denied->value, 0),
                'all' => (int) $counts->sum(),
            ],
            'appeals' => $appeals,
        ]);
    }

    public function update(
        ModeratePlatformAppealRequest $request,
        PlatformAppeal $platformAppeal,
        ManagePlatformAppeals $appeals,
    ): RedirectResponse {
        /** @var User $administrator */
        $administrator = $request->user();
        $action = PlatformAppealAction::from(
            $request->string('action')->toString(),
        );

        $appeals->moderate(
            $platformAppeal,
            $administrator,
            $action,
            $request->string('decision_message')->toString(),
        );

        return to_route('admin.appeals.index')->with(
            'status',
            match ($action) {
                PlatformAppealAction::Review => 'Appeal moved into human review.',
                PlatformAppealAction::Approve => 'Appeal approved and account access restored.',
                PlatformAppealAction::Deny => 'Appeal decision recorded. The account remains restricted.',
            },
        );
    }
}

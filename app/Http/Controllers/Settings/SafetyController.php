<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRelationship;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SafetyController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $relationships = UserRelationship::query()
            ->whereBelongsTo($user, 'actor')
            ->with('target:id,name,handle')
            ->orderBy('type')
            ->latest()
            ->get()
            ->map(fn (UserRelationship $relationship): array => [
                'type' => $relationship->type->value,
                'createdAt' => $relationship->created_at?->toIso8601String(),
                'person' => [
                    'name' => $relationship->target->name,
                    'handle' => $relationship->target->handle,
                ],
            ])
            ->values()
            ->all();

        return Inertia::render('settings/safety', [
            'relationships' => $relationships,
        ]);
    }
}

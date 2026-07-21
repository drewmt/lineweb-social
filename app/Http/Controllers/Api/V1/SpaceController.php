<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SpaceResource;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpaceController extends Controller
{
    public function __invoke(Request $request, Space $space): SpaceResource
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $space);

        $space->loadCount('members');
        $space->setAttribute('is_member', $space->hasMember($user));
        $space->setAttribute('current_role', $space->roleFor($user)?->value);

        return new SpaceResource($space);
    }
}

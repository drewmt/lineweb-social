<?php

namespace App\Http\Controllers\Api\V1;

use App\Community\ProfileHighlightProjection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProfileController extends Controller
{
    public function __invoke(
        Request $request,
        User $profile,
        ProfileHighlightProjection $highlights,
    ): ProfileResource {
        Gate::authorize('view', $profile);
        /** @var User $viewer */
        $viewer = $request->user();
        $profile->loadCount(['followers', 'following']);

        $profile->setAttribute(
            'visible_profile_highlights',
            $highlights->referencesFor($profile, $viewer),
        );

        return new ProfileResource($profile);
    }
}

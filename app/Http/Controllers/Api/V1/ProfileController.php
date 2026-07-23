<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ProfileController extends Controller
{
    public function __invoke(User $profile): ProfileResource
    {
        Gate::authorize('view', $profile);
        $profile->loadCount(['followers', 'following']);

        return new ProfileResource($profile);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Models\User;
use Illuminate\Http\Request;

class CurrentProfileController extends Controller
{
    public function __invoke(Request $request): ProfileResource
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadCount(['followers', 'following']);

        return new ProfileResource($user);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlatformAppealRequest;
use App\Models\User;
use App\Platform\ManagePlatformAppeals;
use Illuminate\Http\RedirectResponse;

class PlatformAppealController extends Controller
{
    public function store(
        StorePlatformAppealRequest $request,
        ManagePlatformAppeals $appeals,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $appeals->submit(
            $user,
            $request->string('statement')->toString(),
        );

        return to_route('account.status')->with(
            'status',
            'Your appeal was submitted for human review.',
        );
    }
}

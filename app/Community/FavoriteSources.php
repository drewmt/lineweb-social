<?php

namespace App\Community;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class FavoriteSources
{
    public function profiles(User $viewer): Builder
    {
        return DB::table('profile_favorites')->where('user_id', $viewer->getKey());
    }

    public function spaces(User $viewer): Builder
    {
        return DB::table('space_favorites')->where('user_id', $viewer->getKey());
    }
}

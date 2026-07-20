<?php

namespace App\Events;

use App\Models\CommentReport;
use Illuminate\Foundation\Events\Dispatchable;

class CommentReported
{
    use Dispatchable;

    public function __construct(public readonly CommentReport $report) {}
}

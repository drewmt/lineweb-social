<?php

namespace App\Events;

use App\Enums\ReportAction;
use App\Models\CommentReport;
use Illuminate\Foundation\Events\Dispatchable;

class CommentReportModerated
{
    use Dispatchable;

    public function __construct(
        public readonly CommentReport $report,
        public readonly ReportAction $action,
    ) {}
}

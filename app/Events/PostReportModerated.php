<?php

namespace App\Events;

use App\Enums\ReportAction;
use App\Models\PostReport;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostReportModerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PostReport $report,
        public readonly ReportAction $action,
    ) {}
}

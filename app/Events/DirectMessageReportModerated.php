<?php

namespace App\Events;

use App\Enums\DirectMessageReportAction;
use App\Models\DirectMessageReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectMessageReportModerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DirectMessageReport $report,
        public DirectMessageReportAction $action,
    ) {}
}

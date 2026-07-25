<?php

namespace App\Events;

use App\Models\DirectMessageReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectMessageReported
{
    use Dispatchable, SerializesModels;

    public function __construct(public DirectMessageReport $report) {}
}

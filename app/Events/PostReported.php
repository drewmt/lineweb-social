<?php

namespace App\Events;

use App\Models\PostReport;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostReported
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly PostReport $report) {}
}

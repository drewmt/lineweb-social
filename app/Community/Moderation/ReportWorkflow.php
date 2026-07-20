<?php

namespace App\Community\Moderation;

use App\Enums\ReportAction;
use App\Enums\ReportStatus;
use Illuminate\Validation\ValidationException;

final class ReportWorkflow
{
    public function nextStatus(ReportStatus $current, ReportAction $action): ReportStatus
    {
        if ($action === ReportAction::Review && $current !== ReportStatus::Open) {
            $this->invalidTransition('Only an open report can be moved into review.');
        }

        if (in_array($action, [ReportAction::Hide, ReportAction::Dismiss], true)
            && ! $current->isActive()) {
            $this->invalidTransition('This report already has a decision. Reopen it before changing the outcome.');
        }

        if ($action === ReportAction::Reopen && $current->isActive()) {
            $this->invalidTransition('This report is already active.');
        }

        return match ($action) {
            ReportAction::Review, ReportAction::Reopen => ReportStatus::Reviewing,
            ReportAction::Hide => ReportStatus::Resolved,
            ReportAction::Dismiss => ReportStatus::Dismissed,
        };
    }

    private function invalidTransition(string $message): never
    {
        throw ValidationException::withMessages(['action' => $message]);
    }
}

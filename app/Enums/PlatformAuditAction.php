<?php

namespace App\Enums;

enum PlatformAuditAction: string
{
    case AdministratorGranted = 'administrator.granted';
    case AdministratorRevoked = 'administrator.revoked';
    case MemberSuspended = 'member.suspended';
    case MemberReinstated = 'member.reinstated';
    case AppealSubmitted = 'appeal.submitted';
    case AppealReviewStarted = 'appeal.reviewing';
    case AppealApproved = 'appeal.approved';
    case AppealDenied = 'appeal.denied';
    case DirectMessageReportReviewStarted = 'direct_message_report.reviewing';
    case DirectMessageReportResolved = 'direct_message_report.resolved';
    case DirectMessageReportDismissed = 'direct_message_report.dismissed';
    case DirectMessageReportReopened = 'direct_message_report.reopened';
}

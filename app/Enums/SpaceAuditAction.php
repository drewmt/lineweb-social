<?php

namespace App\Enums;

enum SpaceAuditAction: string
{
    case InvitationSent = 'invitation.sent';
    case InvitationAccepted = 'invitation.accepted';
    case InvitationRevoked = 'invitation.revoked';
    case MemberRoleChanged = 'member.role_changed';
    case MemberRemoved = 'member.removed';
    case OwnershipTransferred = 'ownership.transferred';
    case PostReportReviewStarted = 'post_report.review_started';
    case PostReportResolved = 'post_report.resolved';
    case PostReportDismissed = 'post_report.dismissed';
    case PostReportReopened = 'post_report.reopened';
    case CommentReportReviewStarted = 'comment_report.review_started';
    case CommentReportResolved = 'comment_report.resolved';
    case CommentReportDismissed = 'comment_report.dismissed';
    case CommentReportReopened = 'comment_report.reopened';
}

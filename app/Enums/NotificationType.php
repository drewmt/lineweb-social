<?php

namespace App\Enums;

enum NotificationType: string
{
    case CommentReply = 'comment_reply';
    case ContentMention = 'content_mention';
    case SpaceModeration = 'space_moderation';
}

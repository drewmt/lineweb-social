<?php

namespace App\Enums;

enum NotificationType: string
{
    case CommentReply = 'comment_reply';
    case SpaceModeration = 'space_moderation';
}

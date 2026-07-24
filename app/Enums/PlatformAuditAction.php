<?php

namespace App\Enums;

enum PlatformAuditAction: string
{
    case AdministratorGranted = 'administrator.granted';
    case AdministratorRevoked = 'administrator.revoked';
    case MemberSuspended = 'member.suspended';
    case MemberReinstated = 'member.reinstated';
}

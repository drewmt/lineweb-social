export type SpaceRole = 'owner' | 'moderator' | 'member';

export type ManagedSpace = {
    name: string;
    slug: string;
    description: string | null;
};

export type ManagedMember = {
    id: number;
    name: string;
    role: SpaceRole;
    canChangeRole: boolean;
    canRemove: boolean;
    canReceiveOwnership: boolean;
};

export type PendingInvitation = {
    id: number;
    email: string;
    role: Exclude<SpaceRole, 'owner'>;
    inviter: string | null;
    expiresAt: string;
    canCancel: boolean;
};

export type AuditEntry = {
    id: number;
    action: string;
    actor: string;
    subject: string | null;
    reason: string | null;
    context: Record<string, unknown> | null;
    createdAt: string;
};

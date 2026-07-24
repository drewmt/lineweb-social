import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export type ContentMention = {
    handle: string;
    name: string;
    url: string;
};

type MentionTextProps = {
    body: string;
    mentions: ContentMention[];
};

const mentionPattern = /@([a-z0-9]+(?:-[a-z0-9]+)*)\b/gi;
const invalidPrefixPattern = /[a-z0-9._/@-]/i;

export function MentionText({ body, mentions }: MentionTextProps) {
    const profiles = new Map(
        mentions.map((mention) => [mention.handle, mention]),
    );
    const content: ReactNode[] = [];
    let cursor = 0;

    for (const match of body.matchAll(mentionPattern)) {
        const index = match.index;
        const handle = match[1]?.toLowerCase();
        const prefix = index > 0 ? body[index - 1] : '';
        const mention = handle ? profiles.get(handle) : undefined;

        if (!mention || invalidPrefixPattern.test(prefix)) {
            continue;
        }

        if (index > cursor) {
            content.push(body.slice(cursor, index));
        }

        content.push(
            <Link
                key={`${index}-${handle}`}
                href={mention.url}
                title={mention.name}
                className="social-focus rounded-sm font-bold text-primary hover:underline"
            >
                {match[0]}
            </Link>,
        );
        cursor = index + match[0].length;
    }

    if (cursor < body.length) {
        content.push(body.slice(cursor));
    }

    return <>{content}</>;
}

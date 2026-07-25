import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export type ContentMention = {
    handle: string;
    name: string;
    url: string;
};

export type ContentTopic = {
    name: string;
    url: string;
};

type MentionTextProps = {
    body: string;
    mentions: ContentMention[];
    topics?: ContentTopic[];
};

const mentionPattern = /@([a-z0-9]+(?:-[a-z0-9]+)*)\b/gi;
const invalidPrefixPattern = /[a-z0-9._/@-]/i;
const topicPattern =
    /#([\p{L}\p{N}](?:[\p{L}\p{N}\p{M}_-]{0,48}[\p{L}\p{N}\p{M}_])?)(?![\p{L}\p{N}\p{M}_-])/giu;
const invalidTopicPrefixPattern = /[\p{L}\p{N}._/#@&?=-]/u;

type ContentLink = {
    index: number;
    length: number;
    label: string;
    url: string;
    title: string;
    kind: 'mention' | 'topic';
};

export function MentionText({ body, mentions, topics = [] }: MentionTextProps) {
    const profiles = new Map(
        mentions.map((mention) => [mention.handle, mention]),
    );
    const availableTopics = new Map(topics.map((topic) => [topic.name, topic]));
    const links: ContentLink[] = [];
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

        links.push({
            index,
            length: match[0].length,
            label: match[0],
            url: mention.url,
            title: mention.name,
            kind: 'mention',
        });
    }

    for (const match of body.matchAll(topicPattern)) {
        const index = match.index;
        const name = match[1]?.toLowerCase();
        const prefix = index > 0 ? body[index - 1] : '';
        const topic = name ? availableTopics.get(name) : undefined;

        if (!topic || invalidTopicPrefixPattern.test(prefix)) {
            continue;
        }

        links.push({
            index,
            length: match[0].length,
            label: match[0],
            url: topic.url,
            title: `Explore #${topic.name}`,
            kind: 'topic',
        });
    }

    links.sort((left, right) => left.index - right.index);

    for (const link of links) {
        if (link.index < cursor) {
            continue;
        }

        if (link.index > cursor) {
            content.push(body.slice(cursor, link.index));
        }

        content.push(
            <Link
                key={`${link.kind}-${link.index}-${link.label}`}
                href={link.url}
                title={link.title}
                className={
                    link.kind === 'topic'
                        ? 'social-focus rounded-sm font-extrabold text-primary decoration-primary/30 underline-offset-3 hover:underline'
                        : 'social-focus rounded-sm font-bold text-primary hover:underline'
                }
            >
                {link.label}
            </Link>,
        );
        cursor = link.index + link.length;
    }

    if (cursor < body.length) {
        content.push(body.slice(cursor));
    }

    return <>{content}</>;
}

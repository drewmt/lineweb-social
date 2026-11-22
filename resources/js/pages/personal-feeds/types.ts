export type PersonalFeed = {
    id: number;
    name: string;
    url: string;
    editUrl: string;
};

export type FeedSource = { id: number; label: string; reference: string };

export const sourceKinds = ['people', 'spaces', 'topics'] as const;
export type SourceKind = (typeof sourceKinds)[number];
export type Sources = Record<SourceKind, FeedSource[]>;

export const sourceLabels: Record<SourceKind, string> = {
    people: 'People',
    spaces: 'Spaces',
    topics: 'Topics',
};

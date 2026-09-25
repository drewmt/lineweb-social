export function shouldAutoPlayVideo(
    active: boolean,
    reducedMotion: boolean,
    pageVisible: boolean,
): boolean {
    return active && !reducedMotion && pageVisible;
}

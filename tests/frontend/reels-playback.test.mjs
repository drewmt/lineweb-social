import assert from 'node:assert/strict';
import test from 'node:test';
import { shouldAutoPlayVideo } from '../../resources/js/components/social/video-playback-policy.ts';

test('only the active visible Reel can autoplay without reduced motion', () => {
    assert.equal(shouldAutoPlayVideo(true, false, true), true);
    assert.equal(shouldAutoPlayVideo(false, false, true), false);
    assert.equal(shouldAutoPlayVideo(true, true, true), false);
    assert.equal(shouldAutoPlayVideo(true, false, false), false);
});

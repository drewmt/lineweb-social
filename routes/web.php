<?php

use App\Community\CommunityOnboarding;
use App\Http\Controllers\AccountStatusController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminExtensionController;
use App\Http\Controllers\Admin\AdminMemberController;
use App\Http\Controllers\Admin\MemberSuspensionController;
use App\Http\Controllers\Admin\MessageReportController as AdminMessageReportController;
use App\Http\Controllers\Admin\PlatformAppealController as AdminPlatformAppealController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommentReportController;
use App\Http\Controllers\CommentReportModerationController;
use App\Http\Controllers\CommunityOnboardingController;
use App\Http\Controllers\DirectMessageReportController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowingFeedController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PlatformAppealController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostDraftController;
use App\Http\Controllers\PostHighlightController;
use App\Http\Controllers\PostImageController;
use App\Http\Controllers\PostPollVoteController;
use App\Http\Controllers\PostReactionController;
use App\Http\Controllers\PostReportController;
use App\Http\Controllers\PostReportModerationController;
use App\Http\Controllers\PostShareController;
use App\Http\Controllers\ProfilePostHighlightController;
use App\Http\Controllers\SavedPostController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\SpaceEventController;
use App\Http\Controllers\SpaceEventRsvpController;
use App\Http\Controllers\SpaceInvitationAcceptanceController;
use App\Http\Controllers\SpaceInvitationController;
use App\Http\Controllers\SpaceInviteLinkAcceptanceController;
use App\Http\Controllers\SpaceInviteLinkController;
use App\Http\Controllers\SpaceManagementController;
use App\Http\Controllers\SpaceMemberController;
use App\Http\Controllers\SpaceMembershipController;
use App\Http\Controllers\SpaceModerationController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\StoryImageController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\UserFollowController;
use App\Http\Controllers\UserRelationshipController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('join/{token}', [SpaceInviteLinkAcceptanceController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->middleware('throttle:space-invite-links')
    ->name('space-invite-links.show');

Route::get('account-status', AccountStatusController::class)
    ->middleware('auth')
    ->name('account.status');
Route::redirect('account-suspended', '/account-status')
    ->middleware('auth')
    ->name('account.suspended');
Route::post('account-status/appeals', [PlatformAppealController::class, 'store'])
    ->middleware(['auth', 'throttle:account-appeals'])
    ->name('account.appeals.store');

Route::middleware(['auth', 'account.active', 'verified'])->group(function () {
    Route::get('getting-started', [CommunityOnboardingController::class, 'show'])
        ->name('onboarding.show');
    Route::post('getting-started/dismiss', [CommunityOnboardingController::class, 'dismiss'])
        ->middleware('throttle:content-management')
        ->name('onboarding.dismiss');
    Route::get('feed', FeedController::class)->name('feed');
    Route::get('stories/create', [StoryController::class, 'create'])->name('stories.create');
    Route::post('spaces/{space:slug}/stories', [StoryController::class, 'store'])
        ->middleware('throttle:story-publishing')
        ->name('spaces.stories.store');
    Route::get('stories/{story}', [StoryController::class, 'show'])->name('stories.show');
    Route::get('stories/{story}/image', StoryImageController::class)->name('stories.image');
    Route::delete('stories/{story}', [StoryController::class, 'destroy'])
        ->middleware('throttle:content-management')
        ->name('stories.destroy');
    Route::get('following', FollowingFeedController::class)->name('following.index');
    Route::get('saved', [SavedPostController::class, 'index'])->name('saved.index');
    Route::get('compose', [PostDraftController::class, 'create'])->name('posts.compose');
    Route::get('drafts', [PostDraftController::class, 'index'])->name('drafts.index');
    Route::post('drafts', [PostDraftController::class, 'store'])
        ->middleware('throttle:post-drafts')
        ->name('drafts.store');
    Route::get('drafts/{post}/edit', [PostDraftController::class, 'edit'])
        ->name('drafts.edit');
    Route::patch('drafts/{post}', [PostDraftController::class, 'update'])
        ->middleware('throttle:post-drafts')
        ->name('drafts.update');
    Route::post('drafts/{post}/publish', [PostDraftController::class, 'publish'])
        ->middleware(['throttle:post-drafts', 'throttle:post-publishing'])
        ->name('drafts.publish');
    Route::delete('drafts/{post}', [PostDraftController::class, 'destroy'])
        ->middleware('throttle:post-drafts')
        ->name('drafts.destroy');
    Route::get('search', SearchController::class)
        ->middleware('throttle:community-search')
        ->name('search');
    Route::get('topics/{topic:name}', TopicController::class)
        ->middleware('throttle:community-search')
        ->name('topics.show');
    Route::get('notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::post('notifications/{notification}/open', [NotificationController::class, 'open'])
        ->whereUuid('notification')
        ->middleware('throttle:notification-actions')
        ->name('notifications.open');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])
        ->whereUuid('notification')
        ->middleware('throttle:notification-actions')
        ->name('notifications.read');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])
        ->middleware('throttle:notification-actions')
        ->name('notifications.read-all');
    Route::get('people', [PeopleController::class, 'index'])->name('people.index');
    Route::get('people/{profile:handle}', [PeopleController::class, 'show'])->name('people.show');
    Route::put('people/{profile:handle}/posts/{post}/highlight', [ProfilePostHighlightController::class, 'store'])
        ->scopeBindings()
        ->middleware('throttle:profile-highlights')
        ->name('people.posts.highlights.store');
    Route::delete('people/{profile:handle}/posts/{post}/highlight', [ProfilePostHighlightController::class, 'destroy'])
        ->scopeBindings()
        ->middleware('throttle:profile-highlights')
        ->name('people.posts.highlights.destroy');
    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('messages/new/{profile:handle}', [MessageController::class, 'compose'])
        ->name('messages.compose');
    Route::post('messages/new/{profile:handle}', [MessageController::class, 'start'])
        ->middleware('throttle:direct-messaging')
        ->name('messages.start');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])
        ->name('messages.show');
    Route::post('messages/{conversation}', [MessageController::class, 'store'])
        ->middleware('throttle:direct-messaging')
        ->name('messages.store');
    Route::post('messages/{conversation}/read', [MessageController::class, 'read'])
        ->middleware('throttle:direct-messaging')
        ->name('messages.read');
    Route::post('messages/{conversation}/reports/{directMessage}', [DirectMessageReportController::class, 'store'])
        ->middleware('throttle:message-reporting')
        ->name('messages.reports.store');
    Route::put('people/{profile:handle}/follow', [UserFollowController::class, 'store'])
        ->middleware('throttle:user-following')
        ->name('people.follow');
    Route::delete('people/{profile:handle}/follow', [UserFollowController::class, 'destroy'])
        ->middleware('throttle:user-following')
        ->name('people.unfollow');
    Route::post('people/{profile:handle}/mute', [UserRelationshipController::class, 'mute'])
        ->middleware('throttle:user-safety')
        ->name('people.mute');
    Route::delete('people/{profile:handle}/mute', [UserRelationshipController::class, 'unmute'])
        ->middleware('throttle:user-safety')
        ->name('people.unmute');
    Route::post('people/{profile:handle}/block', [UserRelationshipController::class, 'block'])
        ->middleware('throttle:user-safety')
        ->name('people.block');
    Route::delete('people/{profile:handle}/block', [UserRelationshipController::class, 'unblock'])
        ->middleware('throttle:user-safety')
        ->name('people.unblock');
    Route::get('spaces', [SpaceController::class, 'index'])->name('spaces.index');
    Route::post('spaces', [SpaceController::class, 'store'])
        ->middleware('throttle:space-creation')
        ->name('spaces.store');
    Route::get('spaces/{space:slug}', [SpaceController::class, 'show'])->name('spaces.show');
    Route::get('spaces/{space:slug}/events', [SpaceEventController::class, 'index'])
        ->name('spaces.events.index');
    Route::post('spaces/{space:slug}/events', [SpaceEventController::class, 'store'])
        ->middleware('throttle:space-events')
        ->name('spaces.events.store');
    Route::get('spaces/{space:slug}/manage', SpaceManagementController::class)
        ->name('spaces.manage');
    Route::get('spaces/{space:slug}/moderation', SpaceModerationController::class)
        ->name('spaces.moderation.index');
    Route::patch('spaces/{space:slug}/moderation/reports/{postReport}', [PostReportModerationController::class, 'update'])
        ->scopeBindings()
        ->middleware('throttle:space-moderation')
        ->name('spaces.moderation.reports.update');
    Route::patch('spaces/{space:slug}/moderation/comment-reports/{commentReport}', [CommentReportModerationController::class, 'update'])
        ->scopeBindings()
        ->middleware('throttle:space-moderation')
        ->name('spaces.moderation.comment-reports.update');
    Route::post('spaces/{space:slug}/membership', [SpaceMembershipController::class, 'store'])
        ->middleware('throttle:space-membership')
        ->name('spaces.memberships.store');
    Route::delete('spaces/{space:slug}/membership', [SpaceMembershipController::class, 'destroy'])
        ->middleware('throttle:space-membership')
        ->name('spaces.memberships.destroy');
    Route::post('spaces/{space:slug}/invitations', [SpaceInvitationController::class, 'store'])
        ->middleware('throttle:space-invitations')
        ->name('spaces.invitations.store');
    Route::delete('spaces/{space:slug}/invitations/{invitation}', [SpaceInvitationController::class, 'destroy'])
        ->middleware('throttle:space-moderation')
        ->name('spaces.invitations.destroy');
    Route::post('spaces/{space:slug}/invite-links', [SpaceInviteLinkController::class, 'store'])
        ->middleware('throttle:space-invite-links')
        ->name('spaces.invite-links.store');
    Route::delete('spaces/{space:slug}/invite-links/{inviteLink}', [SpaceInviteLinkController::class, 'destroy'])
        ->scopeBindings()
        ->middleware('throttle:space-moderation')
        ->name('spaces.invite-links.destroy');
    Route::patch('spaces/{space:slug}/members/{member}/role', [SpaceMemberController::class, 'update'])
        ->middleware('throttle:space-moderation')
        ->name('spaces.members.roles.update');
    Route::delete('spaces/{space:slug}/members/{member}', [SpaceMemberController::class, 'destroy'])
        ->middleware('throttle:space-moderation')
        ->name('spaces.members.destroy');
    Route::put('spaces/{space:slug}/owner', [SpaceMemberController::class, 'transferOwnership'])
        ->middleware('throttle:space-moderation')
        ->name('spaces.owner.update');
    Route::get('space-invitations/{token}', [SpaceInvitationAcceptanceController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('space-invitations.show');
    Route::post('space-invitations/{token}/accept', [SpaceInvitationAcceptanceController::class, 'store'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:space-invitations')
        ->name('space-invitations.accept');
    Route::post('join/{token}', [SpaceInviteLinkAcceptanceController::class, 'store'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:space-invite-links')
        ->name('space-invite-links.accept');
    Route::post('spaces/{space:slug}/posts', [PostController::class, 'store'])
        ->middleware('throttle:post-publishing')
        ->name('spaces.posts.store');
    Route::put('spaces/{space:slug}/posts/{post}/highlight', [PostHighlightController::class, 'store'])
        ->scopeBindings()
        ->middleware('throttle:space-highlights')
        ->name('spaces.posts.highlights.store');
    Route::delete('spaces/{space:slug}/posts/{post}/highlight', [PostHighlightController::class, 'destroy'])
        ->scopeBindings()
        ->middleware('throttle:space-highlights')
        ->name('spaces.posts.highlights.destroy');
    Route::get('posts/{post}', [PostController::class, 'show'])
        ->name('posts.show');
    Route::patch('posts/{post}', [PostController::class, 'update'])
        ->middleware('throttle:content-management')
        ->name('posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])
        ->middleware('throttle:content-management')
        ->name('posts.destroy');
    Route::post('posts/{post}/shares', [PostShareController::class, 'store'])
        ->middleware('throttle:post-publishing')
        ->name('posts.shares.store');
    Route::get('posts/{post}/image', [PostImageController::class, 'primary'])
        ->name('posts.image');
    Route::get('posts/{post}/media/{media}', [PostImageController::class, 'show'])
        ->whereNumber('media')
        ->name('posts.media.show');
    Route::post('posts/{post}/reports', [PostReportController::class, 'store'])
        ->middleware('throttle:post-reporting')
        ->name('posts.reports.store');
    Route::put('posts/{post}/save', [SavedPostController::class, 'store'])
        ->middleware('throttle:post-saving')
        ->name('posts.saves.store');
    Route::delete('posts/{post}/save', [SavedPostController::class, 'destroy'])
        ->middleware('throttle:post-saving')
        ->name('posts.saves.destroy');
    Route::put('posts/{post}/reaction', [PostReactionController::class, 'store'])
        ->middleware('throttle:post-reacting')
        ->name('posts.reactions.store');
    Route::delete('posts/{post}/reaction', [PostReactionController::class, 'destroy'])
        ->middleware('throttle:post-reacting')
        ->name('posts.reactions.destroy');
    Route::put('posts/{post}/poll-vote', [PostPollVoteController::class, 'store'])
        ->middleware('throttle:post-poll-voting')
        ->name('posts.poll-votes.store');
    Route::get('events/{spaceEvent}', [SpaceEventController::class, 'show'])
        ->name('events.show');
    Route::put('events/{spaceEvent}/rsvp', [SpaceEventRsvpController::class, 'store'])
        ->middleware('throttle:space-event-rsvps')
        ->name('events.rsvps.store');
    Route::delete('events/{spaceEvent}/rsvp', [SpaceEventRsvpController::class, 'destroy'])
        ->middleware('throttle:space-event-rsvps')
        ->name('events.rsvps.destroy');
    Route::patch('events/{spaceEvent}/cancel', [SpaceEventController::class, 'cancel'])
        ->middleware('throttle:space-events')
        ->name('events.cancel');
    Route::post('posts/{post}/comments', [CommentController::class, 'store'])
        ->middleware('throttle:comment-publishing')
        ->name('posts.comments.store');
    Route::patch('comments/{comment}', [CommentController::class, 'update'])
        ->middleware('throttle:content-management')
        ->name('comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])
        ->middleware('throttle:content-management')
        ->name('comments.destroy');
    Route::post('comments/{comment}/reports', [CommentReportController::class, 'store'])
        ->middleware('throttle:comment-reporting')
        ->name('comments.reports.store');
    Route::get('dashboard', function (
        Request $request,
        CommunityOnboarding $onboarding,
    ): RedirectResponse {
        $pendingInvite = $request->session()->pull('pending_space_invite');

        if (is_string($pendingInvite) && preg_match('/^[A-Za-z0-9]{64}$/', $pendingInvite) === 1) {
            return to_route('space-invite-links.show', ['token' => $pendingInvite]);
        }

        /** @var User $user */
        $user = $request->user();

        if ($onboarding->shouldGuide($user)) {
            return to_route('onboarding.show');
        }

        return to_route('feed');
    })->name('dashboard');
});

Route::prefix('admin')
    ->middleware([
        'auth',
        'account.active',
        'verified',
        'platform.admin',
        'throttle:platform-administration',
    ])
    ->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('admin.index');
        Route::get('extensions', AdminExtensionController::class)
            ->name('admin.extensions.index');
        Route::get('members', AdminMemberController::class)
            ->name('admin.members.index');
        Route::get('audit', AdminAuditLogController::class)
            ->name('admin.audit.index');
        Route::get('message-reports', [AdminMessageReportController::class, 'index'])
            ->name('admin.message-reports.index');
        Route::patch('message-reports/{directMessageReport}', [AdminMessageReportController::class, 'update'])
            ->name('admin.message-reports.update');
        Route::get('appeals', [AdminPlatformAppealController::class, 'index'])
            ->name('admin.appeals.index');
        Route::patch('appeals/{platformAppeal}', [AdminPlatformAppealController::class, 'update'])
            ->name('admin.appeals.update');
        Route::put('members/{member:handle}/suspension', [MemberSuspensionController::class, 'store'])
            ->name('admin.members.suspension.store');
        Route::delete('members/{member:handle}/suspension', [MemberSuspensionController::class, 'destroy'])
            ->name('admin.members.suspension.destroy');
    });

require __DIR__.'/settings.php';

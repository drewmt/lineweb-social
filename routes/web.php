<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommentReportController;
use App\Http\Controllers\CommentReportModerationController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowingFeedController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostImageController;
use App\Http\Controllers\PostReactionController;
use App\Http\Controllers\PostReportController;
use App\Http\Controllers\PostReportModerationController;
use App\Http\Controllers\SavedPostController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\SpaceInvitationAcceptanceController;
use App\Http\Controllers\SpaceInvitationController;
use App\Http\Controllers\SpaceManagementController;
use App\Http\Controllers\SpaceMemberController;
use App\Http\Controllers\SpaceMembershipController;
use App\Http\Controllers\SpaceModerationController;
use App\Http\Controllers\UserFollowController;
use App\Http\Controllers\UserRelationshipController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('feed', FeedController::class)->name('feed');
    Route::get('following', FollowingFeedController::class)->name('following.index');
    Route::get('saved', [SavedPostController::class, 'index'])->name('saved.index');
    Route::get('search', SearchController::class)
        ->middleware('throttle:community-search')
        ->name('search');
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
    Route::post('spaces/{space:slug}/posts', [PostController::class, 'store'])
        ->middleware('throttle:post-publishing')
        ->name('spaces.posts.store');
    Route::get('posts/{post}', [PostController::class, 'show'])
        ->name('posts.show');
    Route::patch('posts/{post}', [PostController::class, 'update'])
        ->middleware('throttle:content-management')
        ->name('posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])
        ->middleware('throttle:content-management')
        ->name('posts.destroy');
    Route::get('posts/{post}/image', PostImageController::class)
        ->name('posts.image');
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
    Route::redirect('dashboard', '/feed')->name('dashboard');
});

require __DIR__.'/settings.php';

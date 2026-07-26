<x-mail::message>
# Your notification digest

You have {{ $total }} {{ Str::plural('unread update', $total) }} waiting in {{ config('app.name') }}.

@if ($counts['comment_reply'] > 0)
- **{{ $counts['comment_reply'] }}** {{ Str::plural('reply', $counts['comment_reply']) }}
@endif
@if ($counts['content_mention'] > 0)
- **{{ $counts['content_mention'] }}** {{ Str::plural('mention', $counts['content_mention']) }}
@endif
@if ($counts['space_moderation'] > 0)
- **{{ $counts['space_moderation'] }}** moderation {{ Str::plural('alert', $counts['space_moderation']) }}
@endif

@if ($hasMore)
More updates may be waiting. Open your inbox for the complete, current view.
@endif

<x-mail::button :url="route('notifications.index')">
Open notifications
</x-mail::button>

For privacy, this email does not include post text, member names, Space names, or report details. Access is checked again when you open your notification inbox.

You can turn this digest off at any time in your notification settings.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

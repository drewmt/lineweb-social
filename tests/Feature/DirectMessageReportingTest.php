<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\DirectMessageReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirectMessageReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_report_an_incoming_message_once_without_exposing_the_report(): void
    {
        [$reporter, $sender] = User::factory()->count(2)->create();
        [$conversation, $message] = $this->conversationWithMessage($sender, $reporter, 'Private abuse evidence');

        $this->actingAs($reporter)
            ->post(route('messages.reports.store', [$conversation, $message]), [
                'reason' => 'harassment',
                'details' => 'Repeated targeted messages after I asked them to stop.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('direct_message_reports', [
            'direct_message_id' => $message->getKey(),
            'reporter_id' => $reporter->getKey(),
            'reported_user_id' => $sender->getKey(),
            'message_body_snapshot' => 'Private abuse evidence',
            'reason' => 'harassment',
            'status' => ReportStatus::Open->value,
        ]);

        $this->actingAs($reporter)
            ->get(route('messages.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page
                ->where('active.messages.0.canReport', false)
                ->where('active.messages.0.hasReported', true)
                ->where('active.messages.0.reportUrl', route('messages.reports.store', [$conversation, $message]))
                ->missing('active.messages.0.reportReason')
                ->missing('active.messages.0.reportDetails'));

        $this->actingAs($sender)
            ->get(route('messages.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page
                ->where('active.messages.0.canReport', false)
                ->where('active.messages.0.hasReported', false));

        $this->actingAs($reporter)
            ->post(route('messages.reports.store', [$conversation, $message]), [
                'reason' => 'privacy',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('direct_message_reports', 1);
    }

    public function test_outsiders_and_senders_cannot_report_a_message_and_blocks_do_not_remove_reporting_access(): void
    {
        [$reporter, $sender, $outsider] = User::factory()->count(3)->create();
        [$conversation, $message] = $this->conversationWithMessage($sender, $reporter, 'Reportable after a block');

        $this->actingAs($outsider)
            ->post(route('messages.reports.store', [$conversation, $message]), [
                'reason' => 'spam',
            ])
            ->assertForbidden();

        $this->actingAs($sender)
            ->post(route('messages.reports.store', [$conversation, $message]), [
                'reason' => 'spam',
            ])
            ->assertForbidden();

        $this->actingAs($reporter)
            ->post(route('people.block', $sender))
            ->assertRedirect();

        $this->actingAs($reporter)
            ->post(route('messages.reports.store', [$conversation, $message]), [
                'reason' => 'harassment',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    public function test_only_administrators_can_view_the_minimized_message_report_queue(): void
    {
        $administrator = User::factory()->create(['platform_role' => 'administrator']);
        [$reporter, $sender, $member] = User::factory()->count(3)->create();
        [$conversation, $message] = $this->conversationWithMessage($sender, $reporter, 'The exact reported message');
        $this->conversationWithMessage($reporter, $sender, 'Adjacent private text must not appear');

        $report = DirectMessageReport::query()->create([
            'direct_message_id' => $message->getKey(),
            'reporter_id' => $reporter->getKey(),
            'reported_user_id' => $sender->getKey(),
            'reason' => 'harassment',
            'details' => 'This is the context the reporter chose to share.',
            'message_body_snapshot' => $message->body,
            'message_sent_at' => $message->created_at,
            'status' => ReportStatus::Open,
        ]);

        $this->actingAs($member)
            ->get(route('admin.message-reports.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->patch(route('admin.message-reports.update', $report), [
                'action' => 'review',
                'note' => 'A regular member must not review private reports.',
            ])
            ->assertForbidden();

        $this->actingAs($administrator)
            ->get(route('admin.message-reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/message-reports')
                ->where('counts.active', 1)
                ->has('reports.data', 1)
                ->where('reports.data.0.id', $report->getKey())
                ->where('reports.data.0.message.body', 'The exact reported message')
                ->where('reports.data.0.reporter.handle', $reporter->handle)
                ->where('reports.data.0.reportedMember.handle', $sender->handle)
                ->missing('reports.data.0.reporter.email')
                ->missing('reports.data.0.conversation')
                ->where('reports.data.0.actionUrl', route('admin.message-reports.update', $report)));

        $response = $this->actingAs($administrator)
            ->get(route('admin.message-reports.index'));

        $this->assertStringNotContainsString('Adjacent private text must not appear', $response->getContent());
        $this->assertSame($conversation->getKey(), $message->conversation_id);
    }

    public function test_administrator_decisions_are_transactional_reasoned_and_audited(): void
    {
        $administrator = User::factory()->create(['platform_role' => 'administrator']);
        [$reporter, $sender] = User::factory()->count(2)->create();
        [, $message] = $this->conversationWithMessage($sender, $reporter, 'Evidence');
        $report = DirectMessageReport::query()->create([
            'direct_message_id' => $message->getKey(),
            'reporter_id' => $reporter->getKey(),
            'reported_user_id' => $sender->getKey(),
            'reason' => 'harassment',
            'message_body_snapshot' => $message->body,
            'message_sent_at' => $message->created_at,
            'status' => ReportStatus::Open,
        ]);

        $this->actingAs($administrator)
            ->patch(route('admin.message-reports.update', $report), [
                'action' => 'review',
                'note' => 'Review started after checking the submitted evidence.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.message-reports.index'));

        $this->assertSame(ReportStatus::Reviewing, $report->refresh()->status);
        $this->assertSame($administrator->getKey(), $report->reviewed_by);
        $this->assertDatabaseHas('platform_audit_logs', [
            'actor_id' => $administrator->getKey(),
            'subject_user_id' => $sender->getKey(),
            'action' => 'direct_message_report.reviewing',
            'reason' => 'Review started after checking the submitted evidence.',
        ]);

        $this->actingAs($administrator)
            ->patch(route('admin.message-reports.update', $report), [
                'action' => 'resolve',
                'note' => 'Evidence reviewed and the account action was recorded separately.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ReportStatus::Resolved, $report->refresh()->status);
        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'direct_message_report.resolved',
            'subject_user_id' => $sender->getKey(),
        ]);

        $this->actingAs($administrator)
            ->patch(route('admin.message-reports.update', $report), [
                'action' => 'dismiss',
                'note' => 'A decided report must be reopened first.',
            ])
            ->assertSessionHasErrors('action');
    }

    public function test_closed_message_report_evidence_is_pruned_after_the_retention_window(): void
    {
        [$reporter, $sender] = User::factory()->count(2)->create();
        [, $message] = $this->conversationWithMessage($sender, $reporter, 'Evidence');
        $base = [
            'direct_message_id' => $message->getKey(),
            'reporter_id' => $reporter->getKey(),
            'reported_user_id' => $sender->getKey(),
            'reason' => 'privacy',
            'message_body_snapshot' => $message->body,
            'message_sent_at' => $message->created_at,
        ];

        $expired = DirectMessageReport::query()->create($base + [
            'status' => ReportStatus::Resolved,
            'reviewed_at' => now()->subDays(181),
        ]);
        $recent = DirectMessageReport::query()->create(array_merge($base, [
            'direct_message_id' => null,
            'status' => ReportStatus::Dismissed,
            'reviewed_at' => now()->subDays(179),
        ]));
        $active = DirectMessageReport::query()->create(array_merge($base, [
            'direct_message_id' => null,
            'status' => ReportStatus::Reviewing,
            'reviewed_at' => now()->subDays(400),
        ]));

        $this->artisan('message-reports:prune')->assertSuccessful();

        $this->assertDatabaseMissing('direct_message_reports', ['id' => $expired->getKey()]);
        $this->assertDatabaseHas('direct_message_reports', ['id' => $recent->getKey()]);
        $this->assertDatabaseHas('direct_message_reports', ['id' => $active->getKey()]);
    }

    public function test_message_report_submissions_have_a_dedicated_hourly_limit(): void
    {
        [$reporter, $sender] = User::factory()->count(2)->create();
        $conversation = Conversation::between($sender, $reporter);

        foreach (range(1, 11) as $index) {
            $message = DirectMessage::query()->create([
                'conversation_id' => $conversation->getKey(),
                'sender_id' => $sender->getKey(),
                'body' => "Reportable message {$index}",
            ]);

            $response = $this->actingAs($reporter)
                ->post(route('messages.reports.store', [$conversation, $message]), [
                    'reason' => 'spam',
                ]);

            if ($index <= 10) {
                $response->assertSessionHasNoErrors()->assertRedirect();
            } else {
                $response->assertTooManyRequests();
            }
        }

        $this->assertDatabaseCount('direct_message_reports', 10);
    }

    /** @return array{Conversation, DirectMessage} */
    private function conversationWithMessage(User $sender, User $recipient, string $body): array
    {
        $conversation = Conversation::between($sender, $recipient);
        $message = DirectMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $sender->getKey(),
            'body' => $body,
        ]);
        $conversation->update([
            'last_message_id' => $message->getKey(),
            'last_message_at' => $message->created_at,
        ]);

        return [$conversation, $message];
    }
}

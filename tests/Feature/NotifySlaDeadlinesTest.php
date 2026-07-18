<?php

namespace Tests\Feature;

use App\Enums\AssigneeType;
use App\Enums\ProcessInstanceActivityStatus;
use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceActivity;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Notifications\ActivitySlaApproachingNotification;
use App\Notifications\ActivitySlaOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 02-motor-de-execucao.md §5 / 04-integracao-e-notificacoes.md §4, item 3 — job agendado
 * hourly, testado diretamente via `Artisan::call` com o relógio controlado
 * (`$this->travelTo()`).
 */
class NotifySlaDeadlinesTest extends TestCase
{
    use RefreshDatabase;

    private function makeActivity(array $piAttributes = []): ProcessInstanceActivity
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->users()->attach($user);
        $this->actingAs($user);

        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($user, 'createdBy')->published()->create();
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        $workflowActivity = WorkflowActivity::factory()->for($step, 'workflowStep')->create();
        $instance = ProcessInstance::factory()->for($version, 'workflowVersion')->create([
            'organization_id' => $org->id,
            'started_by' => $user->id,
        ]);

        return ProcessInstanceActivity::factory()
            ->for($instance, 'processInstance')
            ->for($workflowActivity, 'workflowActivity')
            ->create(array_merge([
                'assigned_user_id' => $user->id,
                'status' => ProcessInstanceActivityStatus::Pending,
            ], $piAttributes));
    }

    public function test_notifies_overdue_activities_once(): void
    {
        Notification::fake();

        $this->travelTo(now()->subDay());
        $activity = $this->makeActivity([
            'started_at' => now(),
            'due_at' => now()->addHour(),
        ]);
        $this->travelBack();

        $this->artisan('workflows:notify-sla-deadlines')->assertSuccessful();

        Notification::assertSentTo($activity->assignedUser, ActivitySlaOverdueNotification::class);
        $this->assertNotNull($activity->fresh()->sla_overdue_notified_at);

        // Segunda execução não reenvia.
        Notification::fake();
        $this->artisan('workflows:notify-sla-deadlines')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_notifies_approaching_activities_at_80_percent_of_the_deadline(): void
    {
        Notification::fake();

        $this->travelTo(now()->subHours(10));
        $activity = $this->makeActivity([
            'started_at' => now(),
            'due_at' => now()->addHours(10), // 10h de janela total
        ]);
        $this->travelTo(now()->addHours(9)); // 9/10 = 90% consumido, ainda não venceu

        $this->artisan('workflows:notify-sla-deadlines')->assertSuccessful();

        Notification::assertSentTo($activity->assignedUser, ActivitySlaApproachingNotification::class);
        Notification::assertNotSentTo($activity->assignedUser, ActivitySlaOverdueNotification::class);
        $this->assertNotNull($activity->fresh()->sla_warning_notified_at);
    }

    public function test_does_not_notify_before_80_percent_of_the_deadline(): void
    {
        Notification::fake();

        $this->travelTo(now());
        $activity = $this->makeActivity([
            'started_at' => now(),
            'due_at' => now()->addHours(10),
        ]);
        $this->travelTo(now()->addHours(5)); // 50% consumido

        $this->artisan('workflows:notify-sla-deadlines')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($activity->fresh()->sla_warning_notified_at);
    }

    public function test_notifies_all_eligible_role_members_when_queued(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $org->users()->attach([$memberA->id, $memberB->id]);
        $this->actingAs($memberA);
        $role = Role::factory()->for($org)->create();
        $role->users()->attach([$memberA->id, $memberB->id]);

        $workflow = Workflow::factory()->for($org)->for($memberA, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($memberA, 'createdBy')->published()->create();
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        $workflowActivity = WorkflowActivity::factory()->for($step, 'workflowStep')->create([
            'assignee_type' => AssigneeType::Role,
            'assignee_role_id' => $role->id,
        ]);
        $instance = ProcessInstance::factory()->for($version, 'workflowVersion')->create([
            'organization_id' => $org->id,
            'started_by' => $memberA->id,
        ]);

        $this->travelTo(now()->subDay());
        ProcessInstanceActivity::factory()
            ->for($instance, 'processInstance')
            ->for($workflowActivity, 'workflowActivity')
            ->create([
                'assigned_user_id' => null,
                'status' => ProcessInstanceActivityStatus::Pending,
                'started_at' => now(),
                'due_at' => now()->addHour(),
            ]);
        $this->travelBack();

        $this->artisan('workflows:notify-sla-deadlines')->assertSuccessful();

        Notification::assertSentTo($memberA, ActivitySlaOverdueNotification::class);
        Notification::assertSentTo($memberB, ActivitySlaOverdueNotification::class);
    }
}

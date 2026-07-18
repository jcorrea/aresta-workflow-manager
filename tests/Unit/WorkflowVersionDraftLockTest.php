<?php

namespace Tests\Unit;

use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `draft_lock_workflow_id` (01-modelo-de-dados.md §2.2) é o truque de índice único para
 * reforçar "no máximo um draft por workflow" no MySQL/SQLite (sem índice único parcial
 * nativo) — precisa ser uma constraint de banco de verdade, não só validação de aplicação,
 * porque sozinha teria condição de corrida real (CLAUDE.md, "Pontos de atenção do domínio").
 */
class WorkflowVersionDraftLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_second_draft_of_the_same_workflow_violates_the_unique_constraint(): void
    {
        $workflow = Workflow::factory()->create();

        WorkflowVersion::factory()->for($workflow)->create();

        $this->expectException(QueryException::class);

        WorkflowVersion::factory()->for($workflow)->create();
    }

    public function test_a_published_version_does_not_block_a_new_draft(): void
    {
        $workflow = Workflow::factory()->create();

        WorkflowVersion::factory()->for($workflow)->published()->create();

        $draft = WorkflowVersion::factory()->for($workflow)->create();

        $this->assertNotNull($draft->id);
    }

    public function test_a_draft_of_a_different_workflow_does_not_collide(): void
    {
        $workflowA = Workflow::factory()->create();
        $workflowB = Workflow::factory()->create();

        WorkflowVersion::factory()->for($workflowA)->create();
        $draftB = WorkflowVersion::factory()->for($workflowB)->create();

        $this->assertNotNull($draftB->id);
    }
}

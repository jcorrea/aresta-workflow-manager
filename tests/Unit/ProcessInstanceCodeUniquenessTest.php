<?php

namespace Tests\Unit;

use App\Models\ProcessInstance;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `process_instances.code` (01-modelo-de-dados.md §3.1) é o código legível (ex.:
 * `2026-000123`) usado para correlacionar com o mesmo processo em outros sistemas GIITS —
 * precisa ser único globalmente, reforçado no banco.
 */
class ProcessInstanceCodeUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_code_violates_the_unique_constraint(): void
    {
        ProcessInstance::factory()->create(['code' => '2026-000123']);

        $this->expectException(QueryException::class);

        ProcessInstance::factory()->create(['code' => '2026-000123']);
    }
}

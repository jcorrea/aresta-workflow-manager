<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Model só de teste, sem equivalente em `app/Models` — a Fase 0 introduz o mecanismo de
 * isolamento (`BelongsToOrganization`) antes de existir qualquer model de domínio real que o
 * use (Workflow/ProcessInstance/Role chegam na Fase 1). Precisa de cobertura de verdade já
 * aqui, não só quando o primeiro model real existir.
 */
class ScopedFixture extends Model
{
    use BelongsToOrganization;

    protected $table = 'scoped_fixtures';

    protected $fillable = ['organization_id', 'name'];
}

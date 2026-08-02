<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = ['organization_id', 'name', 'slug', 'description', 'created_by', 'current_published_version_id'];

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function instances(): HasManyThrough
    {
        return $this->hasManyThrough(ProcessInstance::class, WorkflowVersion::class);
    }

    public function currentPublishedVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'current_published_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * `slug` é único por organização, não globalmente (01-modelo-de-dados.md §2.1) — mesmo
     * padrão de `BoardController::uniqueSlug()` do GIITS Status, só que escopado.
     */
    public static function uniqueSlug(string $name, int $organizationId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            static::where('organization_id', $organizationId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}

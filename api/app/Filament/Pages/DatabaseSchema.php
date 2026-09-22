<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Superadmin-only (panel gates on User::canAccessPanel()). Introspects the live schema via
 * Laravel's native Schema facade — Laravel 11+ exposes getTables/getColumns/getForeignKeys
 * without doctrine/dbal — and renders it as a Mermaid erDiagram so it never drifts from
 * migrations the way a hand-maintained diagram would.
 */
class DatabaseSchema extends Page
{
    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Database Schema';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected string $view = 'filament.pages.database-schema';

    /**
     * Tables that add noise without adding insight into the app's actual data model. Toggleable
     * from the page — this is just the default state, not a hard exclusion.
     */
    public array $hiddenTables = [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    public function getAllTableNames(): array
    {
        return collect(Schema::getTables())->pluck('name')->sort()->values()->all();
    }

    /**
     * Cached briefly — introspection is a handful of extra DB round trips per load, and the
     * schema itself only changes on deploy, so a short TTL keeps repeat views cheap without
     * risking a stale diagram for long.
     */
    public function getDiagram(): string
    {
        $cacheKey = 'filament:database-schema-diagram:'.md5(implode(',', $this->hiddenTables));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () {
            $tables = collect(Schema::getTables())
                ->pluck('name')
                ->reject(fn (string $name) => in_array($name, $this->hiddenTables, true))
                ->sort()
                ->values();

            $lines = ['erDiagram'];
            $relationships = [];

            foreach ($tables as $table) {
                $lines[] = '    '.$this->mermaidEntity($table).' {';

                foreach (Schema::getColumns($table) as $column) {
                    $type = $this->mermaidType($column['type_name'] ?? $column['type'] ?? 'unknown');
                    $name = $column['name'];
                    $attrs = [];

                    if ($column['auto_increment'] ?? false) {
                        $attrs[] = 'PK';
                    }
                    if (Str::endsWith($name, '_id') && $name !== 'id') {
                        $attrs[] = 'FK';
                    }

                    $suffix = $attrs === [] ? '' : ' '.implode(',', $attrs);

                    $comment = $column['nullable'] ? 'nullable' : 'not null';
                    if (($column['default'] ?? null) !== null) {
                        $comment .= ', default '.$column['default'];
                    }

                    $lines[] = "        {$type} {$name}{$suffix} \"{$this->mermaidComment($comment)}\"";
                }

                $lines[] = '    }';

                foreach (Schema::getForeignKeys($table) as $fk) {
                    $foreignTable = $fk['foreign_table'];

                    if (! $tables->contains($foreignTable)) {
                        continue;
                    }

                    $columnList = implode('_', $fk['columns']);
                    $relationships[] = sprintf(
                        '    %s ||--o{ %s : "%s"',
                        $this->mermaidEntity($foreignTable),
                        $this->mermaidEntity($table),
                        $columnList,
                    );
                }
            }

            return implode("\n", array_merge($lines, $relationships));
        });
    }

    /**
     * Table name (lowercase) => URL of the Filament resource backed by that table, for
     * click-through navigation from a diagram node. Best-effort: resources whose model or
     * index route don't resolve cleanly are silently skipped rather than breaking the page.
     */
    public function getTableResourceUrls(): array
    {
        return collect(Filament::getPanel('admin')->getResources())
            ->reduce(function (array $carry, string $resourceClass) {
                try {
                    $model = $resourceClass::getModel();
                    $table = (new $model)->getTable();
                    $carry[$table] = $resourceClass::getUrl('index');
                } catch (\Throwable) {
                    // Resource has no index route, abstract model, or similar — skip it.
                }

                return $carry;
            }, []);
    }

    protected function mermaidEntity(string $table): string
    {
        // Mermaid entity names can't contain hyphens/dots; table names here are snake_case so
        // this is just a defensive upper-case, not a real sanitizer.
        return Str::upper($table);
    }

    protected function mermaidType(string $type): string
    {
        return Str::of($type)->replaceMatches('/[^a-zA-Z0-9]/', '_')->lower()->toString() ?: 'unknown';
    }

    protected function mermaidComment(string $comment): string
    {
        return str_replace('"', "'", $comment);
    }
}

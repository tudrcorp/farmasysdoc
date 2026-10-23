<?php

namespace App\Services\Hr;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\OrgChart;
use App\Models\OrgChartNode;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class OrgChartComposer
{
    /** @var Collection<int, Employee>|null */
    private ?Collection $employees = null;

    /** @var Collection<int, Branch>|null */
    private ?Collection $branches = null;

    public function fromPrompt(OrgChart $chart, string $prompt): void
    {
        $prompt = trim($prompt);

        if ($prompt === '') {
            throw new InvalidArgumentException('Escribe la estructura que quieres generar.');
        }

        if ($this->isBranchPrompt($prompt)) {
            $this->fromBranches($chart);

            return;
        }

        $this->clear($chart);

        $lines = preg_split('/\R/u', $prompt) ?: [];
        /** @var array<int, int> $stack */
        $stack = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            if (str_contains($line, '>')) {
                $this->ingestPath($chart, $line);
                $stack = [];

                continue;
            }

            $indent = $this->indentLevel($line);
            $content = trim(preg_replace('/^[-*•]\s*/u', '', trim($line)) ?? '');

            if ($content === '') {
                continue;
            }

            if ($indent === 0 && str_contains($content, ',')) {
                foreach (explode(',', $content) as $piece) {
                    $piece = trim($piece);

                    if ($piece !== '') {
                        $this->createFromLabel($chart, null, $piece);
                    }
                }

                $stack = [];

                continue;
            }

            $parentId = null;

            foreach ($stack as $level => $id) {
                if ($level < $indent) {
                    $parentId = $id;
                }
            }

            foreach (array_keys($stack) as $level) {
                if ($level >= $indent) {
                    unset($stack[$level]);
                }
            }

            $node = $this->createFromLabel($chart, $parentId, $content);
            $stack[$indent] = $node->id;
        }

        if ($chart->nodes()->count() === 0) {
            throw new InvalidArgumentException('No reconocí cargos en ese texto.');
        }

        app(OrgChartArranger::class)->arrange($chart->fresh() ?? $chart);
    }

    public function fromBranches(OrgChart $chart): void
    {
        $this->clear($chart);

        $root = $this->make($chart, null, 'Gerencia general', null, null);
        $operations = $this->make($chart, $root->id, 'Operaciones', null, null);
        $this->make($chart, $root->id, 'Administración', null, null);
        $this->make($chart, $root->id, 'Recursos humanos', null, null);

        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();

        foreach ($branches as $branch) {
            $lead = $this->make($chart, $operations->id, 'Regente', null, $branch);
            $this->make($chart, $lead->id, 'Caja', null, $branch);
            $this->make($chart, $lead->id, 'Depósito', null, $branch);
        }

        app(OrgChartArranger::class)->arrange($chart->fresh() ?? $chart);
    }

    private function isBranchPrompt(string $prompt): bool
    {
        if (str_contains($prompt, "\n") || str_contains($prompt, '>')) {
            return false;
        }

        return preg_match('/sucursal/iu', $prompt) === 1;
    }

    private function clear(OrgChart $chart): void
    {
        $chart->nodes()->update(['parent_id' => null]);
        $chart->nodes()->delete();
    }

    private function ingestPath(OrgChart $chart, string $line): void
    {
        $parentId = null;

        foreach (explode('>', $line) as $piece) {
            $piece = trim(preg_replace('/^[-*•]\s*/u', '', trim($piece)) ?? '');

            if ($piece === '') {
                continue;
            }

            $parentId = $this->createFromLabel($chart, $parentId, $piece)->id;
        }
    }

    private function indentLevel(string $line): int
    {
        if (! preg_match('/^([ \t]*)/u', $line, $matches)) {
            return 0;
        }

        $prefix = str_replace("\t", '  ', $matches[1]);

        return intdiv(strlen($prefix), 2);
    }

    private function createFromLabel(OrgChart $chart, ?int $parentId, string $label): OrgChartNode
    {
        [$title, $person] = $this->splitLabel($label);
        $employee = $person !== null ? $this->matchEmployee($person) : null;
        $branch = $this->matchBranch($title.' '.($person ?? ''));

        $existing = OrgChartNode::query()
            ->where('org_chart_id', $chart->id)
            ->where('title', $title)
            ->when(
                $parentId === null,
                fn ($query) => $query->whereNull('parent_id'),
                fn ($query) => $query->where('parent_id', $parentId),
            )
            ->first();

        if ($existing instanceof OrgChartNode) {
            $existing->fill([
                'employee_id' => $existing->employee_id ?? $employee?->id,
                'branch_id' => $existing->branch_id ?? $branch?->id ?? $employee?->branch_id,
            ])->save();

            return $existing;
        }

        return $this->make($chart, $parentId, $title, $employee, $branch);
    }

    private function make(
        OrgChart $chart,
        ?int $parentId,
        string $title,
        ?Employee $employee,
        ?Branch $branch,
    ): OrgChartNode {
        return OrgChartNode::query()->create([
            'org_chart_id' => $chart->id,
            'parent_id' => $parentId,
            'employee_id' => $employee?->id,
            'branch_id' => $branch?->id ?? $employee?->branch_id,
            'title' => $title,
            'position_x' => 0,
            'position_y' => 0,
        ]);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitLabel(string $label): array
    {
        $parts = preg_split('/\s+[—–|]\s+|\s+-\s+/u', $label, 2) ?: [$label];
        $title = trim((string) ($parts[0] ?? ''));
        $person = trim((string) ($parts[1] ?? ''));

        return [
            $title !== '' ? mb_substr($title, 0, 160) : 'Cargo',
            $person !== '' ? $person : null,
        ];
    }

    private function matchEmployee(string $name): ?Employee
    {
        $needle = mb_strtolower(trim($name));

        if ($needle === '') {
            return null;
        }

        $exact = $this->employees()->first(
            fn (Employee $employee): bool => mb_strtolower($employee->fullName()) === $needle,
        );

        if ($exact instanceof Employee) {
            return $exact;
        }

        return $this->employees()->first(
            fn (Employee $employee): bool => str_contains(mb_strtolower($employee->fullName()), $needle),
        );
    }

    private function matchBranch(string $text): ?Branch
    {
        $haystack = mb_strtolower($text);

        return $this->branches()->first(
            fn (Branch $branch): bool => $branch->name !== '' && str_contains($haystack, mb_strtolower($branch->name)),
        );
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employees(): Collection
    {
        return $this->employees ??= Employee::query()->where('is_active', true)->get();
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branches(): Collection
    {
        return $this->branches ??= Branch::query()->where('is_active', true)->orderBy('name')->get();
    }
}

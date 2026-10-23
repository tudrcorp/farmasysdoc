<?php

namespace App\Filament\Pages\Hr;

use App\Filament\Resources\Hr\Concerns\ChecksHrAccess;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\OrgChart;
use App\Models\OrgChartNode;
use App\Services\Hr\OrgChartArranger;
use App\Services\Hr\OrgChartComposer;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use UnitEnum;

class OrgChartBuilder extends Page
{
    use ChecksHrAccess;

    protected static ?string $slug = 'organigrama';

    protected static ?string $navigationLabel = 'Organigrama';

    protected static ?string $title = 'Organigrama';

    protected static string|UnitEnum|null $navigationGroup = 'hr';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::RectangleGroup;

    protected ?string $heading = '';

    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'filament.pages.hr.org-chart-builder';

    public ?int $chartId = null;

    public ?int $nodeId = null;

    public string $chartName = '';

    public string $newChartName = '';

    public string $nodeTitle = '';

    public string $nodeShape = 'card';

    public string $nodeArea = '';

    public ?int $nodeEmployeeId = null;

    public ?int $nodeBranchId = null;

    public ?int $nodeParentId = null;

    public string $employeeQuery = '';

    public string $prompt = '';

    private bool $syncingNode = false;

    public function mount(): void
    {
        $chart = OrgChart::query()->latest('id')->first();

        if ($chart instanceof OrgChart) {
            $this->openChart($chart);
        }
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['fi-org-chart-page'];
    }

    public function createChart(): void
    {
        $name = trim($this->newChartName);

        if ($name === '') {
            $name = 'Organigrama '.(OrgChart::query()->count() + 1);
        }

        $chart = OrgChart::query()->create([
            'name' => mb_substr($name, 0, 160),
            'created_by' => auth()->id(),
        ]);

        $this->newChartName = '';
        $this->openChart($chart);
        $this->forgetLists();
    }

    public function openChart(int|OrgChart $chart): void
    {
        $model = $chart instanceof OrgChart
            ? $chart
            : OrgChart::query()->find($chart);

        if (! $model instanceof OrgChart) {
            return;
        }

        $this->chartId = $model->id;
        $this->chartName = $model->name;
        $this->clearNode();
        $this->forgetLists();
    }

    public function updatedChartName(): void
    {
        if ($this->chartId !== null) {
            $this->renameChart();
        }
    }

    public function updatedNodeTitle(): void
    {
        if (! $this->syncingNode) {
            $this->saveNode();
        }
    }

    public function updatedNodeShape(): void
    {
        if (! $this->syncingNode) {
            $this->saveNode();
        }
    }

    public function updatedNodeArea(): void
    {
        if (! $this->syncingNode) {
            $this->saveNode();
        }
    }

    public function updatedNodeEmployeeId(): void
    {
        if (! $this->syncingNode) {
            $this->saveNode();
        }
    }

    public function updatedNodeBranchId(): void
    {
        if (! $this->syncingNode) {
            $this->saveNode();
        }
    }

    public function updatedNodeParentId(): void
    {
        if (! $this->syncingNode) {
            $this->saveNode();
        }
    }

    public function renameChart(): void
    {
        $chart = $this->currentChart();
        $name = trim($this->chartName);

        if ($name === '') {
            $this->chartName = $chart->name;

            return;
        }

        $chart->update(['name' => mb_substr($name, 0, 160)]);
        $this->chartName = $chart->name;
        $this->forgetLists();
    }

    public function duplicateChart(): void
    {
        $chart = $this->currentChart();
        $copy = OrgChart::query()->create([
            'name' => mb_substr($chart->name.' copia', 0, 160),
            'created_by' => auth()->id(),
        ]);

        $map = [];

        foreach ($chart->nodes()->get() as $node) {
            $clone = $node->replicate();
            $clone->org_chart_id = $copy->id;
            $clone->parent_id = null;
            $clone->save();
            $map[$node->id] = $clone->id;
        }

        foreach ($chart->nodes()->get() as $node) {
            if ($node->parent_id !== null && isset($map[$node->id], $map[$node->parent_id])) {
                OrgChartNode::query()->whereKey($map[$node->id])->update([
                    'parent_id' => $map[$node->parent_id],
                ]);
            }
        }

        $this->openChart($copy);
        $this->forgetLists();
    }

    public function deleteChart(): void
    {
        $chart = $this->currentChart();
        $chart->delete();
        $this->chartId = null;
        $this->chartName = '';
        $this->clearNode();
        $this->forgetLists();

        $next = OrgChart::query()->latest('id')->first();

        if ($next instanceof OrgChart) {
            $this->openChart($next);
        }
    }

    public function addNodeAt(int $x, int $y, ?int $parentId = null, string $shape = 'card'): void
    {
        $chart = $this->currentChart();
        $parent = null;
        $shape = $this->normalizeShape($shape);

        if ($parentId !== null) {
            $parent = $this->nodeInChart($parentId);
        }

        $node = OrgChartNode::query()->create([
            'org_chart_id' => $chart->id,
            'parent_id' => $parent?->id,
            'title' => match ($shape) {
                'person' => 'Nueva persona',
                'unit' => 'Nueva unidad',
                default => 'Nuevo cargo',
            },
            'shape' => $shape,
            'branch_id' => $parent?->branch_id,
            'position_x' => $this->clamp($x),
            'position_y' => $this->clamp($y),
        ]);

        $this->fillNode($node);
        $this->forgetLists();
        $this->pushBoard();
    }

    public function moveNode(int $nodeId, int $x, int $y): void
    {
        $node = $this->nodeInChart($nodeId);
        $node->update([
            'position_x' => $this->clamp($x),
            'position_y' => $this->clamp($y),
        ]);
        $this->pushBoard();
    }

    public function setParent(int $nodeId, ?int $parentId): void
    {
        $node = $this->nodeInChart($nodeId);

        if ($parentId !== null) {
            $parent = $this->nodeInChart($parentId);

            if ($this->createsCycle($node, $parent->id)) {
                Notification::make()
                    ->title('Ese enlace cierra un ciclo')
                    ->body('Un cargo no puede reportarse a alguien que ya está bajo su línea.')
                    ->danger()
                    ->send();

                return;
            }

            $parentId = $parent->id;
        }

        $node->update(['parent_id' => $parentId]);

        if ($this->nodeId === $node->id) {
            $this->nodeParentId = $parentId;
        }

        $this->pushBoard();
    }

    public function selectNode(?int $nodeId): void
    {
        if ($nodeId === null) {
            $this->clearNode();

            return;
        }

        $this->fillNode($this->nodeInChart($nodeId));
    }

    public function saveNode(): void
    {
        if ($this->nodeId === null) {
            return;
        }

        $node = $this->nodeInChart($this->nodeId);
        $title = trim($this->nodeTitle);

        if ($title === '') {
            $this->nodeTitle = $node->title;

            return;
        }

        $parentId = $this->nodeParentId;

        if ($parentId !== null) {
            $parent = $this->nodeInChart($parentId);

            if ($this->createsCycle($node, $parent->id)) {
                Notification::make()
                    ->title('Ese enlace cierra un ciclo')
                    ->danger()
                    ->send();
                $this->nodeParentId = $node->parent_id;

                return;
            }

            $parentId = $parent->id;
        }

        $employeeId = $this->nodeEmployeeId;
        $branchId = $this->nodeBranchId;

        if ($employeeId !== null && ! Employee::query()->whereKey($employeeId)->exists()) {
            $employeeId = null;
            $this->nodeEmployeeId = null;
        }

        if ($branchId !== null && ! Branch::query()->whereKey($branchId)->exists()) {
            $branchId = null;
            $this->nodeBranchId = null;
        }

        $node->update([
            'title' => mb_substr($title, 0, 160),
            'shape' => $this->normalizeShape($this->nodeShape),
            'area' => $this->blankToNull($this->nodeArea),
            'employee_id' => $employeeId,
            'branch_id' => $branchId,
            'parent_id' => $parentId,
        ]);

        $this->syncingNode = true;
        $this->nodeTitle = $node->title;
        $this->nodeShape = $this->normalizeShape((string) $node->shape);
        $this->nodeArea = (string) $node->area;
        $this->syncingNode = false;
        $this->forgetLists();
        $this->pushBoard();
    }

    public function deleteNode(int $nodeId): void
    {
        $node = $this->nodeInChart($nodeId);
        $this->remember();
        $node->children()->update(['parent_id' => $node->parent_id]);
        $node->delete();

        if ($this->nodeId === $nodeId) {
            $this->clearNode();
        }

        $this->forgetLists();
        $this->pushBoard();
    }

    public function arrange(): void
    {
        $chart = $this->currentChart();
        $this->remember();
        app(OrgChartArranger::class)->arrange($chart);
        $this->pushBoard();
    }

    public function ingestPrompt(): void
    {
        $chart = $this->currentChart();
        $this->remember();

        try {
            app(OrgChartComposer::class)->fromPrompt($chart, $this->prompt);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->warning()->send();

            return;
        }

        $this->clearNode();
        $this->forgetLists();
        $this->pushBoard();

        Notification::make()
            ->title('Organigrama generado')
            ->body('Puedes mover los cargos y cambiar las líneas. Deshacer vuelve a la versión anterior.')
            ->success()
            ->send();
    }

    public function seedFromBranches(): void
    {
        $chart = $this->currentChart();
        $activeBranches = Branch::query()->where('is_active', true)->count();
        $this->remember();
        app(OrgChartComposer::class)->fromBranches($chart);
        $this->clearNode();
        $this->forgetLists();
        $this->pushBoard();

        $body = $activeBranches > 0
            ? 'Quedó un núcleo corporativo y un equipo por cada sucursal activa.'
            : 'No hay sucursales activas. Quedó solo el núcleo corporativo.';

        Notification::make()->title('Estructura de cadena lista')->body($body)->success()->send();
    }

    public function undo(): void
    {
        $chart = $this->currentChart();
        $history = $this->history();

        if ($history === []) {
            Notification::make()->title('Nada que deshacer')->send();

            return;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = array_pop($history);
        session()->put($this->historyKey(), $history);

        DB::transaction(function () use ($chart, $rows): void {
            $chart->nodes()->update(['parent_id' => null]);
            $chart->nodes()->delete();

            foreach ($rows as $row) {
                $parentId = $row['parent_id'] ?? null;
                $row['parent_id'] = null;
                OrgChartNode::query()->insert($row);
                $row['parent_id'] = $parentId;
            }

            foreach ($rows as $row) {
                if (($row['parent_id'] ?? null) !== null) {
                    OrgChartNode::query()->whereKey($row['id'])->update([
                        'parent_id' => $row['parent_id'],
                    ]);
                }
            }
        });

        $this->clearNode();
        $this->forgetLists();
        $this->pushBoard();
    }

    /**
     * @return Collection<int, OrgChart>
     */
    #[Computed]
    public function charts(): Collection
    {
        return OrgChart::query()->withCount('nodes')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Employee>
     */
    #[Computed]
    public function employeeChoices(): Collection
    {
        $query = mb_strtolower(trim($this->employeeQuery));

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->filter(function (Employee $employee) use ($query): bool {
                if ($query === '') {
                    return true;
                }

                $haystack = mb_strtolower($employee->fullName().' '.$employee->national_id);

                return str_contains($haystack, $query);
            })
            ->values();

        if ($this->nodeEmployeeId !== null && ! $employees->contains('id', $this->nodeEmployeeId)) {
            $selected = Employee::query()->find($this->nodeEmployeeId);

            if ($selected instanceof Employee) {
                $employees->prepend($selected);
            }
        }

        return $employees->take(50)->values();
    }

    /**
     * @return Collection<int, Branch>
     */
    #[Computed]
    public function branchChoices(): Collection
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return Collection<int, OrgChartNode>
     */
    #[Computed]
    public function parentChoices(): Collection
    {
        if ($this->chartId === null) {
            return collect();
        }

        return OrgChartNode::query()
            ->where('org_chart_id', $this->chartId)
            ->when($this->nodeId !== null, fn ($query) => $query->whereKeyNot($this->nodeId))
            ->orderBy('title')
            ->get();
    }

    /**
     * @return array{chartId: int|null, nodes: list<array<string, mixed>>}
     */
    public function boardPayload(): array
    {
        if ($this->chartId === null) {
            return ['chartId' => null, 'nodes' => []];
        }

        $nodes = OrgChartNode::query()
            ->with(['employee', 'branch'])
            ->where('org_chart_id', $this->chartId)
            ->get();

        return [
            'chartId' => $this->chartId,
            'nodes' => $nodes->map(fn (OrgChartNode $node): array => [
                'id' => $node->id,
                'parentId' => $node->parent_id,
                'title' => $node->title,
                'shape' => $node->shape ?: 'card',
                'area' => $node->area,
                'person' => $node->employee?->fullName(),
                'initials' => $node->employee?->initials() ?? $this->initialsFromTitle($node->title),
                'photo' => $node->employee?->photoUrl(),
                'branch' => $node->branch?->name,
                'vacant' => $node->isVacant(),
                'x' => $node->position_x,
                'y' => $node->position_y,
            ])->values()->all(),
        ];
    }

    private function pushBoard(): void
    {
        $this->dispatch('org-synced', payload: $this->boardPayload());
    }

    private function currentChart(): OrgChart
    {
        $chart = OrgChart::query()->find($this->chartId);

        if (! $chart instanceof OrgChart) {
            throw ValidationException::withMessages([
                'chart' => 'Elige o crea un organigrama.',
            ]);
        }

        return $chart;
    }

    private function nodeInChart(int $nodeId): OrgChartNode
    {
        $node = OrgChartNode::query()
            ->where('org_chart_id', $this->currentChart()->id)
            ->find($nodeId);

        if (! $node instanceof OrgChartNode) {
            throw ValidationException::withMessages([
                'node' => 'Ese cargo no está en este organigrama.',
            ]);
        }

        return $node;
    }

    private function fillNode(OrgChartNode $node): void
    {
        $this->nodeId = $node->id;
        $this->nodeTitle = $node->title;
        $this->nodeShape = $this->normalizeShape((string) $node->shape);
        $this->nodeArea = (string) $node->area;
        $this->nodeEmployeeId = $node->employee_id;
        $this->nodeBranchId = $node->branch_id;
        $this->nodeParentId = $node->parent_id;
    }

    private function clearNode(): void
    {
        $this->nodeId = null;
        $this->nodeTitle = '';
        $this->nodeShape = 'card';
        $this->nodeArea = '';
        $this->nodeEmployeeId = null;
        $this->nodeBranchId = null;
        $this->nodeParentId = null;
        $this->employeeQuery = '';
    }

    private function createsCycle(OrgChartNode $node, int $parentId): bool
    {
        $current = $parentId;
        $guard = 0;

        while ($guard < 100) {
            if ($current === $node->id) {
                return true;
            }

            $next = OrgChartNode::query()
                ->where('org_chart_id', $node->org_chart_id)
                ->whereKey($current)
                ->value('parent_id');

            if ($next === null) {
                return false;
            }

            $current = (int) $next;
            $guard++;
        }

        return true;
    }

    private function remember(): void
    {
        if ($this->chartId === null) {
            return;
        }

        $history = $this->history();
        $history[] = OrgChartNode::query()
            ->where('org_chart_id', $this->chartId)
            ->get()
            ->map(fn (OrgChartNode $node): array => [
                'id' => $node->id,
                'org_chart_id' => $node->org_chart_id,
                'parent_id' => $node->parent_id,
                'employee_id' => $node->employee_id,
                'branch_id' => $node->branch_id,
                'title' => $node->title,
                'shape' => $node->shape ?: 'card',
                'area' => $node->area,
                'position_x' => $node->position_x,
                'position_y' => $node->position_y,
                'created_at' => $node->created_at?->toDateTimeString(),
                'updated_at' => $node->updated_at?->toDateTimeString(),
            ])
            ->all();

        session()->put($this->historyKey(), array_slice($history, -20));
    }

    /**
     * @return list<list<array<string, mixed>>>
     */
    private function history(): array
    {
        $history = session()->get($this->historyKey(), []);

        return is_array($history) ? $history : [];
    }

    private function historyKey(): string
    {
        return 'org-chart-undo.'.($this->chartId ?? 0);
    }

    private function forgetLists(): void
    {
        unset($this->charts, $this->parentChoices, $this->employeeChoices);
    }

    private function blankToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, 160);
    }

    private function normalizeShape(string $shape): string
    {
        return in_array($shape, ['card', 'person', 'unit'], true) ? $shape : 'card';
    }

    private function clamp(int $value): int
    {
        return max(-2000, min(8000, $value));
    }

    private function initialsFromTitle(string $title): string
    {
        $parts = preg_split('/\s+/u', trim($title)) ?: [];
        $letters = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr((string) $part, 0, 1));
        }

        return $letters !== '' ? $letters : '·';
    }
}

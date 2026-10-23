<?php

namespace App\Services\Hr;

use App\Models\OrgChart;
use App\Models\OrgChartNode;
use Illuminate\Support\Collection;

class OrgChartArranger
{
    private const SLOT = 248;

    private const ROW = 158;

    public function arrange(OrgChart $chart): void
    {
        $nodes = $chart->nodes()->get()->keyBy('id');

        if ($nodes->isEmpty()) {
            return;
        }

        $byParent = $nodes->groupBy(fn (OrgChartNode $node): int => (int) ($node->parent_id ?? 0));
        $roots = $nodes
            ->filter(fn (OrgChartNode $node): bool => $node->parent_id === null || ! $nodes->has((int) $node->parent_id))
            ->sortBy([
                ['position_x', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        /** @var array<int, int> $widths */
        $widths = [];
        /** @var array<int, true> $guard */
        $guard = [];
        /** @var array<int, true> $placed */
        $placed = [];
        $cursor = 48;

        foreach ($roots as $root) {
            $width = $this->width($root, $byParent, $widths, $guard);
            $this->place($root, $byParent, $cursor, 48, $widths, $guard, $placed);
            $cursor += $width + 36;
        }
    }

    /**
     * @param  Collection<int|string, Collection<int, OrgChartNode>>  $byParent
     * @param  array<int, int>  $widths
     * @param  array<int, true>  $guard
     */
    private function width(OrgChartNode $node, Collection $byParent, array &$widths, array &$guard): int
    {
        if (isset($widths[$node->id])) {
            return $widths[$node->id];
        }

        if (isset($guard[$node->id])) {
            return self::SLOT;
        }

        $guard[$node->id] = true;
        $children = $this->childrenOf($node, $byParent);

        if ($children->isEmpty()) {
            return $widths[$node->id] = self::SLOT;
        }

        $sum = 0;

        foreach ($children as $child) {
            $sum += $this->width($child, $byParent, $widths, $guard);
        }

        return $widths[$node->id] = max(self::SLOT, $sum);
    }

    /**
     * @param  Collection<int|string, Collection<int, OrgChartNode>>  $byParent
     * @param  array<int, int>  $widths
     * @param  array<int, true>  $guard
     * @param  array<int, true>  $placed
     */
    private function place(
        OrgChartNode $node,
        Collection $byParent,
        int $left,
        int $top,
        array &$widths,
        array &$guard,
        array &$placed,
    ): void {
        if (isset($placed[$node->id])) {
            return;
        }

        $placed[$node->id] = true;
        $width = $this->width($node, $byParent, $widths, $guard);
        $node->position_x = $left + intdiv($width, 2) - 110;
        $node->position_y = $top;
        $node->save();

        $x = $left;

        foreach ($this->childrenOf($node, $byParent) as $child) {
            $childWidth = $this->width($child, $byParent, $widths, $guard);
            $this->place($child, $byParent, $x, $top + self::ROW, $widths, $guard, $placed);
            $x += $childWidth;
        }
    }

    /**
     * @param  Collection<int|string, Collection<int, OrgChartNode>>  $byParent
     * @return Collection<int, OrgChartNode>
     */
    private function childrenOf(OrgChartNode $node, Collection $byParent): Collection
    {
        /** @var Collection<int, OrgChartNode> $children */
        $children = $byParent->get($node->id, collect());

        return $children
            ->sortBy([
                ['position_x', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }
}

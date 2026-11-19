<?php

namespace App\Queries;

use App\Enums\WorkflowStepKind;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Support\Collection;

/**
 * A workflow as a graph (RG-17): its active steps, their exits (an action without exit goes on to
 * the next step), the checks to show, and the geometry of its diagram: steps placed in layers from
 * the first step down, loops drawn on the right. Shared by the web page (SVG) and L2 (PNG).
 */
final class WorkflowGraph
{
    public const NODE_WIDTH = 210;

    public const NODE_HEIGHT = 58;

    public const DECISION_HEIGHT = 84;

    private const H_GAP = 50;

    private const V_GAP = 64;

    private const MARGIN = 24;

    private const LOOP_GAP = 22;

    /** @var array<int, WorkflowStep> */
    public array $steps = [];

    /** @var list<array{from: int, to: int, label: ?string, implicit: bool}> */
    public array $edges = [];

    /** @var list<string> */
    public array $warnings = [];

    public static function for(Workflow $workflow): self
    {
        $graph = new self;
        /** @var Collection<int, WorkflowStep> $steps */
        $steps = $workflow->steps()->active()->with(['transitions.target', 'feature'])->get()->values();
        $graph->steps = $steps->keyBy('id')->all();
        $graph->build($steps);

        return $graph;
    }

    /**
     * Exits of a step as text, e.g. "Oui → 5. Traiter ; Non → 8. Clôturer".
     */
    public function exitsLabel(int $stepId): string
    {
        return collect($this->edges)
            ->where('from', $stepId)
            ->map(fn (array $edge): string => trim(($edge['label'] ?? '').' → '.$this->steps[$edge['to']]->position.'. '.$this->steps[$edge['to']]->name).($edge['implicit'] ? ' (suite)' : ''))
            ->implode(' ; ');
    }

    /**
     * @param  Collection<int, WorkflowStep>  $steps
     */
    private function build(Collection $steps): void
    {
        foreach ($steps as $index => $step) {
            $active = $step->transitions->filter(fn ($transition): bool => isset($this->steps[$transition->target_step_id]));

            foreach ($step->transitions as $transition) {
                if (! isset($this->steps[$transition->target_step_id])) {
                    $this->warnings[] = sprintf('Étape %d « %s » : une sortie mène à une étape archivée.', $step->position, $step->name);
                }
            }

            if ($step->kind === WorkflowStepKind::End) {
                continue;
            }

            if ($step->kind === WorkflowStepKind::Action && $active->isEmpty()) {
                $next = $steps->get($index + 1);
                if ($next !== null) {
                    $this->edges[] = ['from' => $step->id, 'to' => $next->id, 'label' => null, 'implicit' => true];
                }

                continue;
            }

            foreach ($active as $transition) {
                $this->edges[] = ['from' => $step->id, 'to' => $transition->target_step_id, 'label' => $transition->label, 'implicit' => false];
            }

            if ($step->kind === WorkflowStepKind::Decision && $active->count() < 2) {
                $this->warnings[] = sprintf('Étape %d « %s » : une décision a au moins deux sorties (si / sinon).', $step->position, $step->name);
            }
        }

        $first = $steps->first();
        if ($first !== null) {
            $reached = $this->reachableFrom($first->id);
            foreach ($steps as $step) {
                if (! isset($reached[$step->id])) {
                    $this->warnings[] = sprintf('Étape %d « %s » : aucun chemin n’y mène depuis la première étape.', $step->position, $step->name);
                }
            }
        }
    }

    /**
     * @return array<int, true>
     */
    private function reachableFrom(int $start): array
    {
        $seen = [$start => true];
        $queue = [$start];

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($this->edges as $edge) {
                if ($edge['from'] === $current && ! isset($seen[$edge['to']])) {
                    $seen[$edge['to']] = true;
                    $queue[] = $edge['to'];
                }
            }
        }

        return $seen;
    }

    /**
     * Diagram geometry. Edges going back up (loops) are routed along the right side.
     *
     * @return array{width: int, height: int, nodes: list<array{id: int, number: int, kind: string, lines: list<string>, x: int, y: int, w: int, h: int}>, edges: list<array{points: list<array{0: int, 1: int}>, label: ?string, loop: bool, implicit: bool, labelAt: array{0: int, 1: int}}>}
     */
    public function layout(): array
    {
        if ($this->steps === []) {
            return ['width' => 0, 'height' => 0, 'nodes' => [], 'edges' => []];
        }

        $order = array_keys($this->steps);
        $backEdges = $this->backEdges($order[0]);
        $layer = array_fill_keys($order, 0);

        // Longest path from the first step on the forward edges; unreachable steps follow in order.
        $changed = true;
        for ($guard = 0; $changed && $guard < count($order) + 1; $guard++) {
            $changed = false;
            foreach ($this->edges as $index => $edge) {
                if (! isset($backEdges[$index]) && $layer[$edge['to']] < $layer[$edge['from']] + 1) {
                    $layer[$edge['to']] = $layer[$edge['from']] + 1;
                    $changed = true;
                }
            }
        }

        $byLayer = [];
        foreach ($order as $stepId) {
            $byLayer[$layer[$stepId]][] = $stepId;
        }
        ksort($byLayer);

        $widest = max(array_map('count', $byLayer));
        $contentWidth = $widest * self::NODE_WIDTH + ($widest - 1) * self::H_GAP;
        $loops = count($backEdges);
        $width = self::MARGIN * 2 + $contentWidth + ($loops > 0 ? $loops * self::LOOP_GAP + 20 : 0);

        $nodes = [];
        $y = self::MARGIN;
        foreach ($byLayer as $stepIds) {
            $rowHeight = 0;
            $rowWidth = count($stepIds) * self::NODE_WIDTH + (count($stepIds) - 1) * self::H_GAP;
            $x = self::MARGIN + intdiv($contentWidth - $rowWidth, 2);
            foreach ($stepIds as $stepId) {
                $step = $this->steps[$stepId];
                $h = $step->kind === WorkflowStepKind::Decision ? self::DECISION_HEIGHT : self::NODE_HEIGHT;
                $nodes[$stepId] = [
                    'id' => $stepId,
                    'number' => $step->position,
                    'kind' => $step->kind->value,
                    'lines' => $this->wrap($step->position.'. '.$step->name, $step->kind === WorkflowStepKind::Decision ? 22 : 28, $step->kind === WorkflowStepKind::Decision ? 2 : 3),
                    'x' => $x,
                    'y' => $y,
                    'w' => self::NODE_WIDTH,
                    'h' => $h,
                ];
                $rowHeight = max($rowHeight, $h);
                $x += self::NODE_WIDTH + self::H_GAP;
            }
            $y += $rowHeight + self::V_GAP;
        }
        $height = $y - self::V_GAP + self::MARGIN;

        $edges = [];
        $loopIndex = 0;
        foreach ($this->edges as $index => $edge) {
            $from = $nodes[$edge['from']];
            $to = $nodes[$edge['to']];

            if (isset($backEdges[$index])) {
                $lane = self::MARGIN + $contentWidth + 20 + $loopIndex * self::LOOP_GAP;
                $loopIndex++;
                $start = [$from['x'] + $from['w'], $from['y'] + intdiv($from['h'], 2)];
                $end = [$to['x'] + $to['w'], $to['y'] + intdiv($to['h'], 2)];
                $points = [$start, [$lane, $start[1]], [$lane, $end[1]], $end];
                $labelAt = [$start[0] + 6, $start[1] - 6];
            } else {
                $centerX = $from['x'] + intdiv($from['w'], 2);
                $centerY = $from['y'] + intdiv($from['h'], 2);
                $end = [$to['x'] + intdiv($to['w'], 2), $to['y']];

                if ($this->steps[$edge['from']]->kind === WorkflowStepKind::Decision && abs($end[0] - $centerX) > 10) {
                    // A decision sends each exit from the side of its target, so labels never overlap.
                    $start = [$end[0] < $centerX ? $from['x'] : $from['x'] + $from['w'], $centerY];
                    $points = [$start, [$end[0], $centerY], $end];
                    $labelAt = [$end[0] + 6, $centerY + 16];
                } else {
                    $start = [$centerX, $from['y'] + $from['h']];
                    $middle = intdiv($start[1] + $end[1], 2);
                    $points = $start[0] === $end[0] ? [$start, $end] : [$start, [$start[0], $middle], [$end[0], $middle], $end];
                    $labelAt = [$start[0] + 6, $start[1] + 14];
                }
            }

            $edges[] = ['points' => $points, 'label' => $edge['label'], 'loop' => isset($backEdges[$index]), 'implicit' => $edge['implicit'], 'labelAt' => $labelAt];
        }

        return ['width' => $width, 'height' => $height, 'nodes' => array_values($nodes), 'edges' => $edges];
    }

    /**
     * Indexes of the edges that close a loop (towards a step already on the current path).
     *
     * @return array<int, true>
     */
    private function backEdges(int $start): array
    {
        $back = [];
        $state = [];
        $visit = function (int $stepId) use (&$visit, &$back, &$state): void {
            $state[$stepId] = 'open';
            foreach ($this->edges as $index => $edge) {
                if ($edge['from'] !== $stepId) {
                    continue;
                }
                $target = $edge['to'];
                if (($state[$target] ?? null) === 'open') {
                    $back[$index] = true;
                } elseif (! isset($state[$target])) {
                    $visit($target);
                }
            }
            $state[$stepId] = 'done';
        };

        $visit($start);
        foreach (array_keys($this->steps) as $stepId) {
            if (! isset($state[$stepId])) {
                $visit($stepId);
            }
        }

        return $back;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $width, int $maxLines): array
    {
        // Multibyte-safe: wordwrap() counts bytes and could cut an accented letter in two.
        $lines = [];
        $current = '';
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            while (mb_strlen($word) > $width) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }
                $lines[] = mb_substr($word, 0, $width);
                $word = mb_substr($word, $width);
            }
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) > $width) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim(mb_substr($lines[$maxLines - 1], 0, $width - 1)).'…';
        }

        return $lines;
    }
}

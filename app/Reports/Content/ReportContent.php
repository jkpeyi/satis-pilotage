<?php

namespace App\Reports\Content;

/**
 * What a builder produces: control block values and one section per template variable.
 * Serialized as the immutable snapshot of a generation.
 */
final class ReportContent
{
    /** @var array<string, TextSection|TableSection> */
    private array $sections = [];

    /** @var array<string, list<array{caption: string, png: string, width_cm: float}>> images shown after a variable's content */
    private array $figures = [];

    public function __construct(
        public readonly string $product,
        public readonly string $client,
        public readonly string $repository = 'Sans objet',
        public readonly string $commit = 'Sans objet',
    ) {}

    public function set(string $variable, TextSection|TableSection $section): self
    {
        $this->sections[$variable] = $section;

        return $this;
    }

    /**
     * Adds an image (PNG) after the content of a variable, e.g. a workflow diagram after the table.
     */
    public function addFigure(string $variable, string $caption, string $png, float $widthCm): self
    {
        $this->figures[$variable][] = ['caption' => $caption, 'png' => $png, 'width_cm' => $widthCm];

        return $this;
    }

    /**
     * @return list<array{caption: string, png: string, width_cm: float}>
     */
    public function figures(string $variable): array
    {
        return $this->figures[$variable] ?? [];
    }

    /**
     * @return array<string, TextSection|TableSection>
     */
    public function sections(): array
    {
        return $this->sections;
    }

    /**
     * @return array<string, TableSection>
     */
    public function tables(): array
    {
        return array_filter($this->sections, fn ($section): bool => $section instanceof TableSection);
    }

    /**
     * Share of required cells that are filled, from 0 to 100. A report without table is complete.
     */
    public function completeness(): float
    {
        $filled = 0;
        $total = 0;
        foreach ($this->tables() as $table) {
            $stats = $table->completeness();
            $filled += $stats['filled'];
            $total += $stats['total'];
        }

        return $total === 0 ? 100.0 : round($filled * 100 / $total, 2);
    }

    /**
     * Pending items for the "Validation et réserves" block, e.g. "Responsable technique : 3 valeurs à confirmer".
     *
     * @return list<string>
     */
    public function pendingItems(): array
    {
        $items = [];
        foreach ($this->tables() as $table) {
            foreach ($table->completeness()['missing'] as $label => $count) {
                $items[] = sprintf('%s — %s : %d valeur%s à confirmer', $table->title, $label, $count, $count > 1 ? 's' : '');
            }
        }

        return $items;
    }

    public function missingCount(): int
    {
        return array_sum(array_map(fn (TableSection $table): int => array_sum($table->completeness()['missing']), $this->tables()));
    }

    /**
     * Deterministic representation: same data, same snapshot, same hash.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        $sections = [];
        foreach ($this->sections as $variable => $section) {
            $sections[$variable] = $section instanceof TableSection
                ? ['type' => 'table', 'title' => $section->title, 'columns' => $section->columns(), 'rows' => $section->rows()]
                : ['type' => 'text', 'blocks' => $section->blocks()];
        }

        // Images derive from the data above: only their captions are part of the snapshot.
        $figures = array_map(fn (array $list): array => array_column($list, 'caption'), $this->figures);

        return [
            'control' => ['product' => $this->product, 'client' => $this->client, 'repository' => $this->repository, 'commit' => $this->commit],
            'sections' => $sections,
            ...($figures !== [] ? ['figures' => $figures] : []),
        ];
    }
}

<?php

namespace App\Reports\Content;

/**
 * A table replacing one {{xxx.table}} variable. Columns marked required count in completeness:
 * an empty required cell is shown as "À confirmer" and listed in the pending items.
 */
final class TableSection
{
    /** @var list<array{key: string, label: string, required: bool, in_document: bool, link?: bool}> */
    private array $columns = [];

    /** @var list<array<string, string|null>> */
    private array $rows = [];

    public function __construct(public readonly string $title) {}

    /**
     * A column not in the document (same value on every row, e.g. the analyzed commit) is still
     * written in the XLSX annex.
     */
    public function column(string $key, string $label, bool $required = true, bool $inDocument = true, bool $link = false): self
    {
        // "link" is only set when true, so that the snapshots of the other tables do not change.
        $this->columns[] = ['key' => $key, 'label' => $label, 'required' => $required, 'in_document' => $inDocument] + ($link ? ['link' => true] : []);

        return $this;
    }

    /**
     * @param  array<string, string|int|float|null>  $values
     */
    public function row(array $values): self
    {
        $row = [];
        foreach ($this->columns as $column) {
            $value = $values[$column['key']] ?? null;
            $row[$column['key']] = $value === null || trim((string) $value) === '' ? null : (string) $value;
        }
        $this->rows[] = $row;

        return $this;
    }

    /**
     * @return list<array{key: string, label: string, required: bool, in_document: bool, link?: bool}>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<array{key: string, label: string, required: bool, in_document: bool, link?: bool}>
     */
    public function documentColumns(): array
    {
        return array_values(array_filter($this->columns, fn (array $column): bool => $column['in_document']));
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * @return array{filled: int, total: int, missing: array<string, int>}
     */
    public function completeness(): array
    {
        $filled = 0;
        $total = 0;
        $missing = [];

        foreach ($this->columns as $column) {
            if (! $column['required']) {
                continue;
            }

            foreach ($this->rows as $row) {
                $total++;
                if ($row[$column['key']] !== null) {
                    $filled++;
                } else {
                    $missing[$column['label']] = ($missing[$column['label']] ?? 0) + 1;
                }
            }
        }

        return ['filled' => $filled, 'total' => $total, 'missing' => $missing];
    }
}

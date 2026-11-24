<?php

namespace App\Queries;

use App\Enums\ImplementationStatus;
use App\Enums\VersionStatus;
use App\Enums\WorkflowStepKind;
use App\Models\Implementation;
use App\Models\Version;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Collection;

/**
 * How each version of the product realizes a workflow: every step takes the status, in that
 * version, of the catalog feature that realizes it. Nothing is entered per version: the workflow
 * stays the product's standard process, the versions' scopes say what each one implements.
 */
final class WorkflowCoverage
{
    /** A decision or an end without feature. */
    public const NOT_APPLICABLE = 'not_applicable';

    /** An action whose feature is not chosen yet. */
    public const UNDEFINED = 'undefined';

    /** The feature is not attached to the version (out of its scope, RG-05). */
    public const NOT_ATTACHED = 'not_attached';

    /**
     * @param  Collection<int, Version>  $versions
     * @param  Collection<int, WorkflowStep>  $steps
     * @param  array<int, array<int, ImplementationStatus>>  $statuses  version id => feature id => status
     */
    private function __construct(
        public readonly Collection $versions,
        public readonly Collection $steps,
        private readonly array $statuses,
    ) {}

    public static function for(Workflow $workflow): self
    {
        $versions = Version::query()->active()->where('product_id', $workflow->product_id)
            ->with('customer')->get()
            ->sortBy(fn (Version $version): string => ($version->status === VersionStatus::Future ? '1' : '0').$version->name)
            ->values();
        $steps = $workflow->steps()->active()->with('feature')->get();

        $statuses = [];
        $featureIds = $steps->pluck('feature_id')->filter()->unique()->values()->all();
        if ($featureIds !== [] && $versions->isNotEmpty()) {
            Implementation::query()->active()
                ->whereIn('version_id', $versions->modelKeys())
                ->whereIn('feature_id', $featureIds)
                ->get(['version_id', 'feature_id', 'status'])
                ->each(function (Implementation $implementation) use (&$statuses): void {
                    $statuses[$implementation->version_id][(int) $implementation->feature_id] = $implementation->status;
                });
        }

        return new self(new Collection($versions->all()), $steps, $statuses);
    }

    /**
     * The status of the step's feature in the version, or one of the constants above.
     */
    public function cell(WorkflowStep $step, Version $version): ImplementationStatus|string
    {
        if ($step->feature_id === null) {
            return $step->kind === WorkflowStepKind::Action ? self::UNDEFINED : self::NOT_APPLICABLE;
        }

        return $this->statuses[$version->id][$step->feature_id] ?? self::NOT_ATTACHED;
    }

    /**
     * Breaks the process: the feature is out of the version's scope, absent or defective.
     */
    public function isBlocking(ImplementationStatus|string $cell): bool
    {
        return $cell === self::NOT_ATTACHED || in_array($cell, [ImplementationStatus::Absent, ImplementationStatus::Defective], true);
    }

    /**
     * @return array{steps: int, operational: int, blocking: int, other: int, undefined: int}
     */
    public function summary(Version $version): array
    {
        $summary = ['steps' => 0, 'operational' => 0, 'blocking' => 0, 'other' => 0, 'undefined' => 0];
        foreach ($this->steps as $step) {
            $cell = $this->cell($step, $version);
            if ($cell === self::NOT_APPLICABLE) {
                continue;
            }
            $summary['steps']++;
            match (true) {
                $cell === self::UNDEFINED => $summary['undefined']++,
                $cell === ImplementationStatus::Operational => $summary['operational']++,
                $this->isBlocking($cell) => $summary['blocking']++,
                default => $summary['other']++,
            };
        }

        return $summary;
    }

    /**
     * Colors of the diagram nodes for one version: step id => green, amber, red or gray.
     *
     * @return array<int, string>
     */
    public function nodeColors(Version $version): array
    {
        $colors = [];
        foreach ($this->steps as $step) {
            $cell = $this->cell($step, $version);
            $colors[$step->id] = match (true) {
                $cell === self::NOT_APPLICABLE => 'none',
                $cell === self::UNDEFINED => 'gray',
                $cell === ImplementationStatus::Operational => 'green',
                $this->isBlocking($cell) => 'red',
                default => 'amber',
            };
        }

        return $colors;
    }

    public static function label(ImplementationStatus|string $cell): string
    {
        return match ($cell) {
            self::NOT_APPLICABLE => 'Sans objet',
            self::UNDEFINED => 'Fonctionnalité à préciser',
            self::NOT_ATTACHED => 'Non rattachée',
            default => $cell instanceof ImplementationStatus ? $cell->label() : $cell,
        };
    }
}

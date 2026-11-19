<?php

namespace App\Actions\Referential;

use App\Models\Technology;
use Illuminate\Support\Collection;

/**
 * Adds to the technologies referential the cycles used by the estate, so that a technology entered
 * on a repository, a component or an environment does not have to be entered again (EF-107).
 * A new cycle has no end-of-support date: the Technologies screen lists it as to be completed.
 *
 * The cycle of a version follows the granularity already used for that technology (php 8.1,
 * laravel 9); for a technology seen for the first time, major.minor for the few that release
 * that way (php, mysql…), the major version otherwise.
 */
final class RegisterTechnologies
{
    private const MINOR_CYCLE_COMPONENTS = ['php', 'mysql', 'mariadb', 'python', 'ruby', 'perl', 'mongodb', 'redis', 'nginx', 'apache', 'httpd', 'tomcat'];

    /** @var Collection<string, Collection<int, Technology>>|null */
    private ?Collection $known = null;

    /**
     * @param  array<string, string|null>  $stack  component => version, e.g. ['laravel' => '9.52.16']
     * @return int number of cycles added
     */
    public function handle(array $stack): int
    {
        $added = 0;

        foreach ($stack as $component => $version) {
            $component = mb_strtolower(trim((string) $component));
            $version = trim((string) $version);

            if ($component === '' || $version === '' || mb_strlen($component) > 64) {
                continue;
            }

            $cycle = $this->cycleFor($component, $version);
            if ($cycle === null) {
                continue;
            }

            $technology = Technology::query()->firstOrCreate(['component' => $component, 'cycle' => $cycle]);
            if ($technology->wasRecentlyCreated) {
                $this->known = null;
                $added++;
            }
        }

        return $added;
    }

    /**
     * Null when the version has no number ("latest", a branch name) or an existing cycle covers it.
     */
    public function cycleFor(string $component, string $version): ?string
    {
        $clean = ltrim((string) preg_replace('/^[^0-9]*/', '', $version), 'v');
        if (preg_match('/^(\d+)(?:\.(\d+))?/', $clean, $parts) !== 1) {
            return null;
        }

        $cycles = $this->knownCycles($component);
        foreach ($cycles as $cycle) {
            if ($clean === $cycle || str_starts_with($clean, $cycle.'.')) {
                return null;
            }
        }

        $minor = $cycles !== []
            ? collect($cycles)->contains(fn (string $cycle): bool => str_contains($cycle, '.'))
            : in_array($component, self::MINOR_CYCLE_COMPONENTS, true);

        return $minor && isset($parts[2]) ? $parts[1].'.'.$parts[2] : $parts[1];
    }

    /**
     * @return list<string>
     */
    private function knownCycles(string $component): array
    {
        $this->known ??= Technology::query()->get(['component', 'cycle'])->groupBy('component');

        return $this->known->get($component, collect())->pluck('cycle')->map(fn ($cycle): string => (string) $cycle)->values()->all();
    }
}

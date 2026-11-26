<?php

namespace App\Actions\Referential;

use App\Enums\SupportStatus;
use App\Models\Technology;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Matches each component of a stack with the technologies referential (EF-503).
 *
 * A version matches the longest cycle it starts with: "9.52.16" matches cycle "9",
 * "8.1.27" matches "8.1" rather than "8". Constraints such as "^8.1" are read as "8.1".
 */
final class ResolveStackSupport
{
    public const WARNING_MONTHS = 6;

    /** @var Collection<string, Collection<int, Technology>>|null */
    private ?Collection $technologies = null;

    /**
     * @param  array<string, string>  $stack
     * @return array<string, array{version: string, technology: ?Technology, status: SupportStatus}>
     */
    public function handle(array $stack, ?CarbonInterface $at = null): array
    {
        $result = [];
        ksort($stack);

        foreach ($stack as $component => $version) {
            $technology = $this->match(strtolower($component), $version);

            $result[$component] = [
                'version' => $version,
                'technology' => $technology,
                'status' => $this->status($technology, $at),
            ];
        }

        return $result;
    }

    /**
     * The cycle of the referential a version belongs to: the longest cycle it starts with
     * (9.52.16 → 9), or null when the referential does not know it.
     */
    public function match(string $component, string $version): ?Technology
    {
        $clean = ltrim((string) preg_replace('/^[^0-9]*/', '', $version), 'v');

        return $this->technologiesFor($component)
            ->filter(fn (Technology $technology): bool => $clean === $technology->cycle
                || str_starts_with($clean, $technology->cycle.'.'))
            ->sortByDesc(fn (Technology $technology): int => strlen($technology->cycle))
            ->first();
    }

    private function status(?Technology $technology, ?CarbonInterface $at): SupportStatus
    {
        return match (true) {
            $technology === null || $technology->end_of_support === null => SupportStatus::Unknown,
            $technology->isOutOfSupport($at) => SupportStatus::OutOfSupport,
            $technology->endsSupportWithin(self::WARNING_MONTHS, $at) => SupportStatus::EndingSoon,
            default => SupportStatus::Supported,
        };
    }

    /**
     * @return Collection<int, Technology>
     */
    private function technologiesFor(string $component): Collection
    {
        $this->technologies ??= Technology::query()->get()->groupBy('component');

        return $this->technologies->get($component, collect());
    }
}

<?php
namespace App\Patterns\Composite;

use App\Patterns\Adapter\SponsorData;
use Closure;

// COMPOSITE PATTERN - the "leaf".
// One rule: a label to show the user, plus a small function that answers yes or no.
// Example: new CheckItem('Booth setup arranged', fn ($s) => $s->setupArranged())

/** The "leaf": one yes/no rule. */
final class CheckItem implements Check
{
    // $label is shown when the rule fails; $test is the function that checks it.
    public function __construct(private string $label, private Closure $test) {}

    public function passes(SponsorData $sponsor): bool
    {
        // Run the stored function on this sponsor.
        return (bool) ($this->test)($sponsor);
    }

    public function missing(SponsorData $sponsor): array
    {
        // Nothing missing if it passes; otherwise report this rule's label.
        return $this->passes($sponsor) ? [] : [$this->label];
    }
}

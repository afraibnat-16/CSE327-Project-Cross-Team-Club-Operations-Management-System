<?php
namespace App\Patterns\Composite;

use App\Patterns\Adapter\SponsorData;

// COMPOSITE PATTERN - the "composite".
// Holds a list of children. Each child is a Check, so it can be a single
// CheckItem or another CheckGroup. The group just asks each child in turn.

/** The "composite": a group that passes only when every child passes. Children can be groups too. */
final class CheckGroup implements Check
{
    // $name describes the group; $children are the rules (or groups) inside it.
    /** @param Check[] $children */
    public function __construct(private string $name, private array $children) {}

    public function passes(SponsorData $sponsor): bool
    {
        // One failing child is enough to fail the whole group.
        foreach ($this->children as $child) {
            if (! $child->passes($sponsor)) {
                return false;
            }
        }

        return true;
    }

    public function missing(SponsorData $sponsor): array
    {
        // Collect what is missing from every child into one flat list.
        // A child group adds its own children's items, so nesting is handled automatically.
        $missing = [];
        foreach ($this->children as $child) {
            $missing = array_merge($missing, $child->missing($sponsor));
        }

        return $missing;
    }
}

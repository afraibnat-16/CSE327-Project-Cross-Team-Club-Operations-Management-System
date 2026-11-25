<?php

namespace App\Patterns\Composite;

use App\Patterns\Adapter\SponsorData;

// COMPOSITE PATTERN - the "component" interface.
// Both a single rule (CheckItem) and a group of rules (CheckGroup) follow it,
// so code can ask "does it pass?" without caring which one it has.

/** A single rule OR a group of rules. Callers treat both the same way. */
interface Check
{
    // True when the sponsor meets this rule (or every rule in the group).
    public function passes(SponsorData $sponsor): bool;

    /** @return string[] plain-language list of what is still missing */
    public function missing(SponsorData $sponsor): array;
}

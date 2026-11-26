<?php

namespace App\Patterns\Observer;

// OBSERVER PATTERN - the "observer" interface.
// Every observer has one method, update(), which the publisher calls.

/** Anything that wants to be told when a sponsor changes stage. */
interface StageObserver
{
    public function update(StageChange $change): void;
}

<?php

namespace App\Patterns\Observer;

// OBSERVER PATTERN - the "subject" (also called the publisher).
// It keeps a list of observers. New reactions to a stage change are added by
// attaching another observer, without changing this class or the service.

/**
 * The subject. Observers sign up with attach(); when a stage changes,
 * notify() tells every one of them. The publisher knows nothing about what they do.
 */
final class StageChangePublisher
{
    /** @var StageObserver[] */
    private array $observers = [];

    // Sign an observer up to be told about changes.
    public function attach(StageObserver $observer): void
    {
        $this->observers[] = $observer;
    }

    // Remove an observer: keep every observer that is NOT this one.
    // array_values() renumbers the list (0, 1, 2...) after the removal.
    public function detach(StageObserver $observer): void
    {
        $this->observers = array_values(array_filter(
            $this->observers,
            fn (StageObserver $o) => $o !== $observer
        ));
    }

    // Tell every signed-up observer what happened, one after another.
    public function notify(StageChange $change): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($change);
        }
    }
}

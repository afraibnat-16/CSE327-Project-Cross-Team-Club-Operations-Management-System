<?php

namespace App\Patterns\Support;

use App\Enums\Team;
use App\Models\User;

/**
 * Who is doing something. A small plain object, so the pattern classes
 * never need to know about the User database model.
 */
final class Actor
{
    // "readonly": once an Actor is made, its id, name and team cannot change.
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly Team $team,
    ) {}

    // Build an Actor from the logged-in member (the users table).
    // The team column is normally already a Team enum (the model casts it);
    // if it is still a plain string, Team::from() turns it into one.
    // Note: a member with no team cannot become an Actor (Team::from(null) fails).
    public static function fromUser(User $user): self
    {
        $team = $user->team instanceof Team ? $user->team : Team::from($user->team);

        return new self($user->id, $user->name, $team);
    }
}

<?php

namespace App\Patterns\Builder;

use InvalidArgumentException;

// BUILDER PATTERN
// A sponsor has many fields, and most are optional. Instead of one giant
// array, we set the fields one small step at a time, then call build().
// build() checks the result, so a sponsor can never be made half-empty.
//
// Example:
//   $data = (new SponsorBuilder)
//       ->name('Kite Coffee')
//       ->contact('Rafi Ahmed', '01700000000')
//       ->needsElectricity()
//       ->build();
//   Sponsor::create($data);
class SponsorBuilder
{
    // The fields collected so far, starting with sensible defaults.
    private array $data = [
        'type' => 'Company',
        'requires_electricity' => false,
        'setup_arranged' => false,
    ];

    // Each step below saves one piece of data and returns $this (the same
    // builder), so the next step can be chained on with "->".

    public function name(string $name): static
    {
        $this->data['organization_name'] = $name;

        return $this;
    }

    public function type(string $type): static
    {
        $this->data['type'] = $type;

        return $this;
    }

    public function contact(string $name, string $phone, string $email = ''): static
    {
        $this->data['contact_name'] = $name;
        $this->data['contact_phone'] = $phone;
        $this->data['contact_email'] = $email;

        return $this;
    }

    public function amount(float $amount): static
    {
        $this->data['agreed_amount'] = $amount;

        return $this;
    }

    public function items(string $items): static
    {
        $this->data['items'] = $items;

        return $this;
    }

    public function needsElectricity(bool $yes = true): static
    {
        $this->data['requires_electricity'] = $yes;

        return $this;
    }

    // The last step: check the data and hand it back as an array.
    public function build(): array
    {
        // A sponsor without a name makes no sense, so refuse to build it.
        if (empty($this->data['organization_name'])) {
            throw new InvalidArgumentException('A sponsor needs an organization name.');
        }

        return $this->data;
    }
}

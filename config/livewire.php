<?php

// Livewire settings. Only the ones we change are listed; Livewire fills in
// its own defaults for everything else.

return [

    // The layout wrapped around full-page Livewire/Volt pages (e.g. Settings).
    // Livewire 4's default is "layouts::app", which this project does not have,
    // so we point it at our existing layout: resources/views/components/layouts/app.blade.php
    'component_layout' => 'components.layouts.app',

];

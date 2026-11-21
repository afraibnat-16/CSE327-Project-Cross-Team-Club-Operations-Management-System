<?php

namespace App\Enums;

// The team a member belongs to.
// The string on the right is what gets saved in the database.
enum Team: string
{
    case Corporate = 'corporate';
    case Admin = 'admin';
    case Provision = 'provision';
    case Coordinator = 'coordinator';
}

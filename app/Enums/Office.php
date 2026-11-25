<?php
namespace App\Enums;

// The three university offices that must approve a sponsor, in the order they happen.
// The string on the right is what gets saved in the approvals.office column.
enum Office: string
{
    case StudentAffairs = 'student_affairs';
    case OpsManagement  = 'ops_management';
    case GatePass       = 'security_gate_pass';

    // A human-friendly name to show on screen.
    public function label(): string
    {
        return match ($this) {
            self::StudentAffairs => 'Student Affairs',
            self::OpsManagement  => 'Operations & Management',
            self::GatePass       => 'Security Office gate passes',
        };
    }
}

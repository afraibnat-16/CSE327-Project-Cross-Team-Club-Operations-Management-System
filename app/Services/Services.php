<?php
namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Notice;
use App\Patterns\Facade\StageTracker;
use App\Patterns\Observer\ActivityLogObserver;
use App\Patterns\Observer\MemberNotifier;
use App\Patterns\Observer\StageChangePublisher;
use App\Patterns\Proxy\SponsorService;
use App\Patterns\Proxy\SponsorServiceInterface;
use App\Patterns\Proxy\SponsorServiceProxy;
use App\Patterns\Singleton\ActivityLogger;

// This is the only file that knows about the database tables AND the pattern
// classes at the same time. The patterns stay database-free; here we hand them
// small functions that do the saving.

/**
 * The one place that connects all the pieces. Screens call Services::sponsors()
 * and never build the pieces themselves.
 */
final class Services
{
    // Builds the full chain and returns the proxy (screens only ever get the proxy).
    public static function sponsors(): SponsorServiceInterface
    {
        // The Singleton logger saves each entry to the activity_logs table.
        $logger = ActivityLogger::getInstance();
        $logger->setWriter(fn (array $e) => ActivityLog::create([
            'sponsor_id' => $e['sponsorId'],
            'user_id'    => $e['userId'],
            'action'     => $e['action'],
            'details'    => $e['details'],
        ]));

        // The Observer publisher, with its two observers.
        $publisher = new StageChangePublisher();
        $publisher->attach(new ActivityLogObserver($logger));
        $publisher->attach(new MemberNotifier(
            fn (string $team, int $sponsorId, string $message) => Notice::create([
                'team'       => $team,
                'sponsor_id' => $sponsorId,
                'message'    => $message,
            ])
        ));

        // The real service, wrapped in the permission-checking proxy.
        $real = new SponsorService(new StageTracker(), $publisher, $logger);

        return new SponsorServiceProxy($real, $logger);
    }
}

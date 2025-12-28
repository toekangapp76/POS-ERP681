<?php

namespace Modules\Gym\Console;

use Illuminate\Console\Command;
use App\Business;
use Carbon\Carbon;
use Modules\Gym\Services\DeferredRevenueService;

class ProcessDeferredRevenue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gym:process-deferred-revenue 
                            {--business= : Business ID (optional, process all if not specified)}
                            {--date= : Process as of date (default: today)}
                            {--dry-run : Show what would be processed without actually processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process deferred revenue recognition for gym memberships';

    /**
     * @var DeferredRevenueService
     */
    protected $deferredService;

    /**
     * Create a new command instance.
     */
    public function __construct(DeferredRevenueService $deferredService)
    {
        parent::__construct();
        $this->deferredService = $deferredService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $businessId = $this->option('business');
        $dateOption = $this->option('date');
        $dryRun = $this->option('dry-run');

        $asOfDate = $dateOption ? Carbon::parse($dateOption) : Carbon::now();

        $this->info('======================================');
        $this->info('Deferred Revenue Recognition Process');
        $this->info('======================================');
        $this->info('Processing as of: ' . $asOfDate->format('Y-m-d'));
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get businesses to process
        if ($businessId) {
            $businesses = Business::where('id', $businessId)->get();
        } else {
            $businesses = Business::all();
        }

        $totalProcessed = 0;
        $totalAmount = 0;

        foreach ($businesses as $business) {
            $this->info('');
            $this->info("Processing Business: {$business->name} (ID: {$business->id})");
            $this->line(str_repeat('-', 50));

            // Get summary before processing
            $summary = $this->deferredService->getSummary($business->id, $asOfDate);
            
            $this->table(
                ['Metric', 'Amount'],
                [
                    ['Total Pending', number_format($summary['total_pending'], 2)],
                    ['Total Recognized', number_format($summary['total_recognized'], 2)],
                    ['Due for Recognition', number_format($summary['due_for_recognition'], 2)],
                ]
            );

            if ($summary['due_for_recognition'] <= 0) {
                $this->info('No pending recognitions due for this business.');
                continue;
            }

            if ($dryRun) {
                // Show what would be processed
                $dueSchedules = \Modules\Gym\Entities\GymDeferredRevenue::forBusiness($business->id)
                    ->pending()
                    ->where('recognition_date', '<=', $asOfDate->toDateString())
                    ->with(['transaction.contact', 'gymPackage'])
                    ->get();

                $rows = [];
                foreach ($dueSchedules as $schedule) {
                    $rows[] = [
                        $schedule->id,
                        $schedule->transaction->contact->name ?? 'N/A',
                        $schedule->gymPackage->name ?? 'N/A',
                        $schedule->recognition_date->format('Y-m-d'),
                        number_format($schedule->recognition_amount, 2),
                    ];
                }

                $this->table(
                    ['ID', 'Member', 'Package', 'Recognition Date', 'Amount'],
                    $rows
                );

                $this->warn("Would process " . count($dueSchedules) . " recognition(s)");
            } else {
                // Actually process
                $systemUserId = 1; // System user ID
                $processed = $this->deferredService->processRecognition($business->id, $systemUserId, $asOfDate);

                $processedCount = count($processed);
                $processedAmount = collect($processed)->sum('recognition_amount');

                $totalProcessed += $processedCount;
                $totalAmount += $processedAmount;

                $this->info("Processed: {$processedCount} recognition(s)");
                $this->info("Total Amount: " . number_format($processedAmount, 2));

                // Show details
                if ($processedCount > 0) {
                    $rows = [];
                    foreach ($processed as $schedule) {
                        $rows[] = [
                            $schedule->id,
                            $schedule->transaction->contact->name ?? 'N/A',
                            $schedule->recognition_date->format('Y-m-d'),
                            number_format($schedule->recognition_amount, 2),
                        ];
                    }

                    $this->table(
                        ['ID', 'Member', 'Date', 'Amount'],
                        $rows
                    );
                }
            }
        }

        $this->info('');
        $this->info('======================================');
        $this->info('SUMMARY');
        $this->info('======================================');
        
        if (!$dryRun) {
            $this->info("Total Recognitions Processed: {$totalProcessed}");
            $this->info("Total Amount Recognized: " . number_format($totalAmount, 2));
        }

        $this->info('Process completed at: ' . now()->format('Y-m-d H:i:s'));

        return 0;
    }
}

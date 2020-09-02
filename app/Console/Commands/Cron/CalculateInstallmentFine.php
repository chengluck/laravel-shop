<?php

namespace App\Console\Commands\Cron;

use App\Models\Installment;
use App\Models\InstallmentItem;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateInstallmentFine extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:calculate-installment-fine';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '计算分期付款逾期费';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        InstallmentItem::query()
            ->with(['installment'])
            ->whereHas('installment', function($query){
                $query->where('status', Installment::STATUS_REPAYING);
            })
            ->where('due_date','<=', Carbon::now())
            ->whereNull('paid_at')
            ->chunkById(1000, function($items){
                foreach($items as $item){
                    $overdueDays = Carbon::now()->diffInDays($item->due_date);
                    $base = big_number($item->base)->add($item->fee)->getValue();
                    $fine = big_number($base)
                        ->multiply($overdueDays)
                        ->multiply($item->installment->fine_rate)
                        ->divide(100)
                        ->getValue();

                    $fine = big_number($fine)->compareTo($base) === 1 ? $base : $fine;

                    $item->update([
                        'fine' => $fine,
                    ]);
                }
            });
    }
}

<?php

namespace App\Console\Commands;

use App\SimilaritasJaroWinkler;
use Illuminate\Console\Command;

class ClearSimilarityCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'similarity-cache:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear similarity cache';

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
        $this->info(sprintf("Clearing the table %s", (new SimilaritasJaroWinkler())->getTable()));
        SimilaritasJaroWinkler::query()->delete();
        return 0;
    }
}

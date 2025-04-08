<?php

namespace PlinCode\KmlParser\Commands;

use Illuminate\Console\Command;

class KmlParserCommand extends Command
{
    public $signature = 'kml-parser';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}

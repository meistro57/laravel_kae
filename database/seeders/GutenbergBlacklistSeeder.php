<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class GutenbergBlacklistSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('kae:import-gutenberg-blacklist', [
            '--path' => base_path('../kae/gutenberg_blacklist.json'),
        ]);

        $output = Artisan::output();
        $this->command->getOutput()->write($output);
    }
}

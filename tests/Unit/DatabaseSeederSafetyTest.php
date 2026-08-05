<?php

declare(strict_types=1);

namespace Tests\Unit;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PostSeeder;
use Mockery;
use Tests\TestCase;

class DatabaseSeederSafetyTest extends TestCase
{
    public function test_database_seeder_only_invokes_the_normal_post_seeder(): void
    {
        $seeder = Mockery::mock(DatabaseSeeder::class)->makePartial();
        $seeder->shouldReceive('call')->once()->with(PostSeeder::class);

        $seeder->run();

        $this->addToAssertionCount(1);
    }
}

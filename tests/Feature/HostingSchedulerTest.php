<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class HostingSchedulerTest extends TestCase
{
    private function retryEvent()
    {
        return collect(Schedule::events())->first(
            fn ($event) => str_contains(
                $event->command ?? '',
                'hosting:retry-telegram'
            )
        );
    }

    public function test_telegram_retry_is_scheduled_every_five_minutes(): void
    {
        $event = $this->retryEvent();

        $this->assertNotNull($event);

        $this->assertSame('*/5 * * * *', $event->expression);
    }

    public function test_telegram_retry_is_due_at_minute_25_but_not_26(): void
    {
        $event = $this->retryEvent();

        $this->assertNotNull($event);

        $dueTime = now()->startOfHour()->setMinute(25)->setSecond(0);

        $this->travelTo($dueTime);

        $this->assertTrue(
            $event->isDue($this->app),
            'Retry Telegram seharusnya berjalan pada menit ke-25.'
        );

        $this->travelTo($dueTime->copy()->addMinute());

        $this->assertFalse(
            $event->isDue($this->app),
            'Retry Telegram tidak seharusnya berjalan pada menit ke-26.'
        );
    }
}

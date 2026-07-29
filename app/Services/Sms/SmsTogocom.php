<?php

namespace App\Services\Sms;

/** Togocom / Yas Togo — API SMS marchande. */
class SmsTogocom extends PasserelleSmsBase
{
    public function code(): string
    {
        return 'togocom';
    }

    public function nom(): string
    {
        return 'Togocom (Yas Togo)';
    }
}

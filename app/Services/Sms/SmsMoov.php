<?php

namespace App\Services\Sms;

/** Moov Africa Togo — API SMS marchande. */
class SmsMoov extends PasserelleSmsBase
{
    public function code(): string
    {
        return 'moov';
    }

    public function nom(): string
    {
        return 'Moov Africa';
    }
}

<?php
declare(strict_types=1);

namespace App;

enum RequestState
{
    case Terminated;
    case Reading;
    case Connecting;
    case WaitingToWrite;

    public static function isTerminated(RequestState $s)
    {
        return $s === static::Terminated;
    }

    public static function isReading(RequestState $s)
    {
        return $s === static::Reading;
    }

    public static function isConnecting(RequestState $s)
    {
        return $s === static::Connecting;
    }

    public static function isWaitingToWrite(RequestState $s)
    {
        return $s === static::WaitingToWrite;
    }
}


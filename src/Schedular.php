<?php
declare(strict_types=1);

namespace App;

class Schedular
{
    /**
    * @var HttpRequest[]
    */
    protected array $requests = [];

    public function add(HttpRequest $r)
    {
        $this->requests[] = $r;
    }

    public function run()
    {
        while (!empty($this->requests)) {
            $read = $write = [];
            $except = null;

            foreach ($this->requests as $id => $r) {
                $r->checkTimout();

                if (RequestState::isTerminated($r->state)) {
                    unset($this->requests[$id]);
                    continue;
                }

                if (RequestState::isReading($r->state)) {
                    $read[] = $r->stream;
                }

                if (RequestState::isConnecting($r->state) || RequestState::isWaitingToWrite($r->state)) {
                    $write[] = $r->stream;
                }

                if (RequestState::isConnecting($r->state)) {
                    $r->state = RequestState::WaitingToWrite;
                }
            }

            if (empty($read) && empty($write)) break;

            $modified = @stream_select($read, $write, $except, 0, 100000);

            if ($modified === false) break;

            foreach ($this->requests as $r) {
                if (RequestState::isReading($r->state) && in_array($r->stream, $read, true)) {
                    $r->read();
                } elseif (RequestState::isWaitingToWrite($r->state) && in_array($r->stream, $write, true)) {
                    $r->write();
                }

                if (RequestState::isTerminated($r->state) && $r->fiber->isSuspended()) {
                    $r->fiber->resume($r);
                }
            }
        }
    }
}

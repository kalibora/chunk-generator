<?php

namespace Kalibora\ChunkGenerator;

class ChunkGenerator implements \Countable
{
    private $chunkSize = 100;
    private $max = 0;
    private $findChunk;
    private $onBeforeChunk;
    private $onAfterChunk;

    public function __construct(
        int $max,
        callable $findChunk,
        ?callable $onBeforeChunk = null,
        ?callable $onAfterChunk = null,
        int $chunkSize = 100
    ) {
        $this->max = $max;
        $this->findChunk = $findChunk;
        $this->onBeforeChunk = $onBeforeChunk;
        $this->onAfterChunk = $onAfterChunk;
        $this->chunkSize = $chunkSize;
    }

    public function __invoke() : \Generator
    {
        $cnt = 0;

        while (true) {
            $start = $cnt * $this->chunkSize + 1;
            $end = ($cnt + 1) * $this->chunkSize;
            $cnt++;

            if ($start > $this->max) {
                break;
            }

            if ($this->findChunk) {
                $chunk = call_user_func($this->findChunk, $start, $end, $cnt);
            } else {
                $chunk = [];
            }

            if (is_array($chunk) || $chunk instanceof \Countable) {
                $chunkSize = count($chunk);
            } else {
                $chunkSize = null;
            }

            if ($this->onBeforeChunk) {
                call_user_func($this->onBeforeChunk, $start, $end, $cnt);
            }

            if ($chunkSize === null || $chunkSize > 0) {
                foreach ($chunk as $data) {
                    yield $data;
                }
            }

            if ($this->onAfterChunk) {
                call_user_func($this->onAfterChunk, $start, $end, $cnt);
            }
        }
    }

    public function count() : int
    {
        return $this->max;
    }
}

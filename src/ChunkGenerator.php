<?php

namespace Kalibora\ChunkGenerator;

class ChunkGenerator
{
    protected $chunkSize = 100;
    protected $max = 0;
    protected $findChunk;
    protected $onBeforeChunk;
    protected $onAfterChunk;

    public static function builder() : ChunkGenerator
    {
        return new class() extends ChunkGenerator {
            public function __construct()
            {
                parent::__construct(0, function () {});
            }

            public function setChunkSize(int $chunkSize) : self
            {
                $this->chunkSize = $chunkSize;

                return $this;
            }

            public function setMax(int $max) : self
            {
                $this->max = $max;

                return $this;
            }

            public function setFindChunk(callable $findChunk) : self
            {
                $this->findChunk = $findChunk;

                return $this;
            }

            public function onBeforeChunk(callable $onBeforeChunk) : self
            {
                $this->onBeforeChunk = $onBeforeChunk;

                return $this;
            }

            public function onAfterChunk(callable $onAfterChunk) : self
            {
                $this->onAfterChunk = $onAfterChunk;

                return $this;
            }

            public function build() : ChunkGenerator
            {
                return new ChunkGenerator($this->max, $this->findChunk, $this->onBeforeChunk, $this->onAfterChunk, $this->chunkSize);
            }
        };
    }

    public function __construct(
        int $max,
        callable $findChunk,
        ?callable $onBeforeChunk = null,
        ?callable $onAfterChunk = null,
        ?int $chunkSize = 100
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
}

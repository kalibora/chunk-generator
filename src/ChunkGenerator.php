<?php

namespace Kalibora\ChunkGenerator;

use Countable;
use Generator;

class ChunkGenerator implements Countable
{
    /** @var int */
    private $chunkSize = 100;
    /** @var int */
    private $max = 0;
    /** @var callable(int,int,int):iterable<mixed>|null */
    private $findChunk;
    /** @var callable(int,int,int,iterable<mixed>):void|null */
    private $onBeforeChunk;
    /** @var callable(int,int,int,iterable<mixed>):void|null */
    private $onAfterChunk;
    /** @var callable(mixed):mixed|null */
    private $onBeforeDatum;
    /** @var callable(mixed):mixed|null */
    private $onAfterDatum;

    /**
     * @param callable(int,int,int):iterable<mixed>|null      $findChunk
     * @param callable(int,int,int,iterable<mixed>):void|null $onBeforeChunk
     * @param callable(int,int,int,iterable<mixed>):void|null $onAfterChunk
     * @param callable(mixed):mixed|null                      $onBeforeDatum
     * @param callable(mixed):mixed|null                      $onAfterDatum
     */
    public function __construct(
        int $chunkSize,
        int $max,
        ?callable $findChunk,
        ?callable $onBeforeChunk = null,
        ?callable $onAfterChunk = null,
        ?callable $onBeforeDatum = null,
        ?callable $onAfterDatum = null,
    ) {
        $this->chunkSize = $chunkSize;
        $this->max = $max;
        $this->findChunk = $findChunk;
        $this->onBeforeChunk = $onBeforeChunk;
        $this->onAfterChunk = $onAfterChunk;
        $this->onBeforeDatum = $onBeforeDatum;
        $this->onAfterDatum = $onAfterDatum;
    }

    /**
     * @return Generator<mixed>
     */
    public function __invoke() : Generator
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

            if (is_array($chunk) || $chunk instanceof Countable) {
                $chunkSize = count($chunk);
            } else {
                $chunkSize = null;
            }

            if ($this->onBeforeChunk) {
                call_user_func($this->onBeforeChunk, $start, $end, $cnt, $chunk);
            }

            if ($chunkSize === null || $chunkSize > 0) {
                /** @var iterable<mixed> $chunk */
                foreach ($chunk as $datum) {
                    if ($this->onBeforeDatum) {
                        $datum = call_user_func($this->onBeforeDatum, $datum);
                    }

                    yield $datum;

                    if ($this->onAfterDatum) {
                        $datum = call_user_func($this->onAfterDatum, $datum);
                    }
                }
            }

            if ($this->onAfterChunk) {
                call_user_func($this->onAfterChunk, $start, $end, $cnt, $chunk);
            }
        }
    }

    public function count() : int
    {
        return $this->max;
    }
}

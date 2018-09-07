<?php

namespace Kalibora\ChunkGenerator;

class ChunkGeneratorBuilder
{
    private $chunkSize = 100;
    private $max = 0;
    private $findChunk;
    private $onBeforeChunk;
    private $onAfterChunk;

    public static function fromArray(array $array) : self
    {
        return (new self())
            ->setMax(count($array))
            ->setFindChunk(function ($start, $end, $cnt) use ($array) {
                $len = $end - $start + 1;

                return array_slice($array, $start - 1, (int) $len);
            })
        ;
    }

    public function __construct()
    {
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
        return new ChunkGenerator(
            $this->max,
            $this->findChunk,
            $this->onBeforeChunk,
            $this->onAfterChunk,
            $this->chunkSize
        );
    }
}

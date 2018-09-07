<?php

namespace Kalibora\ChunkGenerator;

use PHPUnit\Framework\TestCase;

class ChunkGeneratorTest extends TestCase
{
    public function testCallingSequenceIsCorrect()
    {
        $data = [];

        $gen = ChunkGenerator::builder()
            ->setChunkSize(2)
            ->setMax(10)
            ->setFindChunk(function ($start, $end) {
                return range($start, $end);
            })
            ->onBeforeChunk(function () use (&$data) {
                $data[] = 'B';
            })
            ->onAfterChunk(function () use (&$data) {
                $data[] = 'A';
            })
            ->build()
        ;

        $this->assertCount(10, $gen);

        foreach ($gen() as $value) {
            $data[] = $value;
        }

        $this->assertEquals(
            'B 1 2 A B 3 4 A B 5 6 A B 7 8 A B 9 10 A',
            implode(' ', $data)
        );
    }
}

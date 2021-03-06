<?php

namespace Kalibora\ChunkGenerator;

use PHPUnit\Framework\TestCase;

class ChunkGeneratorTest extends TestCase
{
    public function testCallingSequenceIsCorrect()
    {
        $data = [];

        $gen = (new ChunkGeneratorBuilder())
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
            ->onBeforeDatum(function ($datum) {
                if ($datum === 5) {
                    return 'five';
                }

                return $datum;
            })
            ->onAfterDatum(function ($datum) {
                if ($datum === 6) {
                    return 'nothing changes';
                }

                return $datum;
            })
            ->build()
        ;

        $this->assertCount(10, $gen);

        foreach ($gen() as $value) {
            $data[] = $value;
        }

        $this->assertEquals(
            'B 1 2 A B 3 4 A B five 6 A B 7 8 A B 9 10 A',
            implode(' ', $data)
        );
    }

    public function testFromArray()
    {
        $data = [];

        $gen = ChunkGeneratorBuilder::fromArray(range(1, 10))
            ->setChunkSize(2)
            ->setMax(10)
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

    public function testFromArray2()
    {
        $data = [];

        $gen = (new ChunkGeneratorBuilder())->setArray(range(1, 10))
            ->setChunkSize(2)
            ->setMax(10)
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

    public function testFromDoctrineQueryBuilder()
    {
        $this->markTestSkipped('TODO: implement test');
    }
}

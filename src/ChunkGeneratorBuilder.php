<?php

namespace Kalibora\ChunkGenerator;

use Doctrine\ORM\QueryBuilder;

class ChunkGeneratorBuilder
{
    private $chunkSize = 100;
    private $max = 0;
    private $findChunk;
    private $onBeforeChunk;
    private $onAfterChunk;
    private $onBeforeDatum;
    private $onAfterDatum;

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

    public static function fromDoctrineQueryBuilder(QueryBuilder $qb) : self
    {
        $manager = $qb->getEntityManager();
        $entities = $qb->getRootEntities();
        $aliases = $qb->getRootAliases();
        $entity = $entities[0];
        $alias = $aliases[0];
        $meta = $manager->getClassMetadata($entity);
        $idFields = $meta->getIdentifierFieldNames();
        $idField = array_shift($idFields);

        $maxId = (int) $qb
            ->select("MAX({$alias}.{$idField})")
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (new self())
            ->setMax($maxId)
            ->setFindChunk(function ($start, $end, $cnt) use ($qb, $alias, $idField) {
                return $qb
                    ->andWhere("{$alias}.{$idField} BETWEEN :start AND :end")
                    ->orderBy("{$alias}.{$idField}", 'ASC')
                    ->setParameter('start', $start)
                    ->setParameter('end', $end)
                    ->getQuery()
                    ->iterate()
                ;
            })
            ->onBeforeDatum(function ($datum) {
                return current($datum);
            })
            ->onAfterChunk(function () use ($manager) {
                $manager->clear();
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

    public function onBeforeDatum(callable $onBeforeDatum) : self
    {
        $this->onBeforeDatum = $onBeforeDatum;

        return $this;
    }

    public function onAfterDatum(callable $onAfterDatum) : self
    {
        $this->onAfterDatum = $onAfterDatum;

        return $this;
    }

    public function build() : ChunkGenerator
    {
        return new ChunkGenerator(
            $this->chunkSize,
            $this->max,
            $this->findChunk,
            $this->onBeforeChunk,
            $this->onAfterChunk,
            $this->onBeforeDatum,
            $this->onAfterDatum
        );
    }
}

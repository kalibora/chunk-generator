<?php

namespace Kalibora\ChunkGenerator;

use Doctrine\ORM\NoResultException;
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

    public static function fromDoctrineQueryBuilder(QueryBuilder $qb, array $specifiedIds = [], bool $fetchJoinCollection = false, bool $useBetween = true) : self
    {
        $manager = $qb->getEntityManager();
        $entities = $qb->getRootEntities();
        $aliases = $qb->getRootAliases();
        $entity = $entities[0];
        $alias = $aliases[0];
        $meta = $manager->getClassMetadata($entity);
        $idFields = $meta->getIdentifierFieldNames();
        $idField = array_shift($idFields);
        assert($idField !== null);

        if ($useBetween) {
            $maxId = self::getMaxId($qb, $alias, $idField);
        } else {
            $maxId = self::getCountId($qb, $alias, $idField);
        }

        $qbChunk = clone $qb;

        if ($useBetween) {
            $qbChunk
                ->andWhere("{$alias}.{$idField} BETWEEN :KaliboraChunkGeneratorStart AND :KaliboraChunkGeneratorEnd")
                ->orderBy("{$alias}.{$idField}", 'ASC')
            ;
        } else {
            $qbChunk->addOrderBy("{$alias}.{$idField}", 'ASC');
        }

        $isSpecifiedIds = false;
        $sortedIds = [];
        if (count($specifiedIds) > 0) {
            $qbChunk
                ->andWhere("{$alias}.{$idField} IN (:KaliboraChunkGeneratorIds)")
                ->setParameter('KaliboraChunkGeneratorIds', $specifiedIds)
            ;

            $isSpecifiedIds = true;
            $sortedIds = $specifiedIds;
            sort($sortedIds, \SORT_NUMERIC);
        }

        return (new self())
            ->setMax($maxId)
            ->setFindChunk(function ($start, $end, $cnt) use ($qbChunk, $isSpecifiedIds, &$sortedIds, $fetchJoinCollection, $useBetween) {
                if (! self::containsLeastOne($start, $end, $isSpecifiedIds, $sortedIds)) {
                    return [];
                }

                if ($useBetween) {
                    $query = $qbChunk
                        ->setParameter('KaliboraChunkGeneratorStart', $start)
                        ->setParameter('KaliboraChunkGeneratorEnd', $end)
                        ->getQuery()
                    ;
                } else {
                    $len = $end - $start + 1;

                    $query = $qbChunk
                        ->setMaxResults((int) $len)
                        ->setFirstResult($start - 1)
                        ->getQuery()
                    ;
                }

                if ($fetchJoinCollection) {
                    return $query->getResult();
                }

                return $query->iterate();
            })
            ->onBeforeDatum(function ($datum) use ($fetchJoinCollection) {
                if ($fetchJoinCollection) {
                    return $datum;
                }

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

    private static function containsLeastOne(int $start, int $end, bool $isSpecifiedIds, array &$sortedIds) : bool
    {
        if (! $isSpecifiedIds) {
            // unknown
            return true;
        }

        $contains = false;
        $nextRequiredIds = [];

        foreach ($sortedIds as $id) {
            if ($id < $start) {
                continue;
            }

            if ($id <= $end) {
                $contains = true;

                continue;
            }

            $nextRequiredIds[] = $id;
        }

        $sortedIds = $nextRequiredIds;

        return $contains;
    }

    private static function getMaxId(QueryBuilder $qb, string $alias, string $idField) : int
    {
        $qbMax = clone $qb;

        $qbMax->select("MAX({$alias}.{$idField})");

        if ($qbMax->getDQLPart('groupBy')) {
            $qbMax
                ->orderBy("{$alias}.{$idField}", 'DESC')
                ->setMaxResults(1)
            ;
        }

        try {
            $maxId = $qbMax->getQuery()->getSingleScalarResult();
        } catch (NoResultException $e) {
            $maxId = 0;
        }

        return (int) $maxId;
    }

    private static function getCountId(QueryBuilder $qb, string $alias, string $idField) : int
    {
        $qbCount = clone $qb;

        $qbCount->select("COUNT({$alias}.{$idField})");

        try {
            $count = $qbCount->getQuery()->getSingleScalarResult();
        } catch (NoResultException $e) {
            $count = 0;
        }

        return (int) $count;
    }
}

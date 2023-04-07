<?php

namespace Kalibora\ChunkGenerator;

use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;

class ChunkGeneratorBuilder
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
     * @param array<mixed> $array
     */
    public static function fromArray(array $array) : self
    {
        return (new self())->setArray($array);
    }

    /**
     * @param array<mixed> $specifiedIds
     */
    public static function fromDoctrineQueryBuilder(QueryBuilder $qb, array $specifiedIds = [], bool $fetchJoinCollection = false, bool $useBetween = true) : self
    {
        return (new self())->setDoctrineQueryBuilder(
            $qb,
            $specifiedIds,
            $fetchJoinCollection,
            $useBetween
        );
    }

    public function __construct()
    {
        $this->reset();
    }

    public function reset() : self
    {
        $this->chunkSize = 100;
        $this->max = 0;
        $this->findChunk = null;
        $this->onBeforeChunk = null;
        $this->onAfterChunk = null;
        $this->onBeforeDatum = null;
        $this->onAfterDatum = null;

        return $this;
    }

    /**
     * @param array<mixed> $array
     */
    public function setArray(array $array) : self
    {
        return $this
            ->setMax(count($array))
            ->setFindChunk(function (int $start, int $end, int $cnt) use ($array) : iterable {
                $len = $end - $start + 1;

                return array_slice($array, $start - 1, (int) $len);
            })
        ;
    }

    /**
     * @param array<mixed> $specifiedIds
     */
    public function setDoctrineQueryBuilder(
        QueryBuilder $qb,
        array $specifiedIds = [],
        bool $fetchJoinCollection = false,
        bool $useBetween = true
    ) : self {
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

        $this
            ->setMax($maxId)
            ->setFindChunk(function (int $start, int $end, int $cnt) use ($qbChunk, $isSpecifiedIds, &$sortedIds, $fetchJoinCollection, $useBetween) : iterable {
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
                    $result = $query->getResult();
                    /* @var iterable<mixed> $result */

                    return $result;
                }

                return $query->iterate();
            })
            /* @var array|object $datum */
            ->onBeforeDatum(function ($datum) use ($fetchJoinCollection) {
                if ($fetchJoinCollection) {
                    return $datum;
                }

                /* @var array<mixed>|object $datum */
                return current($datum);
            })
            ->onAfterChunk(function (int $start, int $end, int $cnt, iterable $chunk) use ($manager) : void {
                $manager->clear();
            })
        ;

        return $this;
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

    /**
     * @param callable(int,int,int):iterable<mixed>|null $findChunk
     */
    public function setFindChunk(?callable $findChunk) : self
    {
        $this->findChunk = $findChunk;

        return $this;
    }

    /**
     * @param callable(int,int,int,iterable<mixed>):void|null $onBeforeChunk
     */
    public function onBeforeChunk(?callable $onBeforeChunk) : self
    {
        $this->onBeforeChunk = $onBeforeChunk;

        return $this;
    }

    /**
     * @param callable(int,int,int,iterable<mixed>):void|null $onAfterChunk
     */
    public function onAfterChunk(?callable $onAfterChunk) : self
    {
        $this->onAfterChunk = $onAfterChunk;

        return $this;
    }

    /**
     * @param callable(mixed):mixed|null $onBeforeDatum
     */
    public function onBeforeDatum(?callable $onBeforeDatum) : self
    {
        $this->onBeforeDatum = $onBeforeDatum;

        return $this;
    }

    /**
     * @param callable(mixed):mixed|null $onAfterDatum
     */
    public function onAfterDatum(?callable $onAfterDatum) : self
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

    /**
     * @param array<mixed> $sortedIds
     */
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
            /* @var scalar $maxId */
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
            /* @var scalar $count */
        } catch (NoResultException $e) {
            $count = 0;
        }

        return (int) $count;
    }
}

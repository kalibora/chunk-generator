<?php

namespace Kalibora\ChunkGenerator;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

/**
 * Optimize the query for performance
 */
final class QueryOptimizer
{
    /**
     * Optimize the query for the maximum value (For MAX() or COUNT())
     */
    public function optimizeForMax(QueryBuilder $qb) : QueryBuilder
    {
        $qbForMax = clone $qb;

        $rootAlias = $qb->getRootAliases()[0];

        // Do not require ORDER BY
        $qbForMax->resetDQLPart('orderBy');

        // SELECT statements other than the main table are not necessary
        $qbForMax->resetDQLPart('select')->addSelect($rootAlias);

        // Narrow down to only JOIN the tables used in WHERE
        return self::optimizeJoin($qbForMax);
    }

    /**
     * Narrow down to only JOIN the tables used in WHERE
     */
    private static function optimizeJoin(QueryBuilder $qb) : QueryBuilder
    {
        // 1. Gather the aliases used in WHERE
        $whereAliases = [];
        self::gatherAlias($qb->getDQLPart('where'), $whereAliases);
        $whereAliases = array_unique($whereAliases);

        // 2. Checking JOIN dependencies
        $joinParts = $qb->getDQLPart('join');
        assert(is_array($joinParts));
        $joinDependencyMap = self::createJoinDependencyMap($joinParts);

        // 3. Gather the aliases used in WHERE or its dependencies
        $rootAlias = $qb->getRootAliases()[0];
        $requiredAliases = [];
        foreach ($whereAliases as $whereAlias) {
            $requiredAliases[] = $whereAlias;

            self::gatherJoinDependency($rootAlias, $whereAlias, $joinDependencyMap, $requiredAliases);
        }

        $requiredAliases = array_unique($requiredAliases);

        // 4. Narrow down to just the required aliases
        $requiredJoins = [];
        foreach ($joinParts as $rootAlias => $joins) {
            foreach ($joins as $join) {
                if (in_array($join->getAlias(), $requiredAliases, true)) {
                    $requiredJoins[$rootAlias][] = $join;
                }
            }
        }
        $qb->resetDQLPart('join');
        foreach ($requiredJoins as $rootAlias => $joins) {
            foreach ($joins as $join) {
                $qb->add('join', [$rootAlias => $join], true);
            }
        }

        return $qb;
    }

    /**
     * Gather the aliases used from the Expr of DQL
     *
     * @param list<string> &$aliases
     */
    private static function gatherAlias(mixed $expr, array &$aliases) : void
    {
        $expr_list = null;

        if (is_array($expr)) {
            $expr_list = $expr;
        } elseif ($expr instanceof Expr\Andx
            || $expr instanceof Expr\Orx
            || $expr instanceof Expr\GroupBy
            || $expr instanceof Expr\Literal
            || $expr instanceof Expr\OrderBy
        ) {
            $expr_list = $expr->getParts();
        }

        if ($expr_list !== null) {
            foreach ($expr_list as $ex) {
                self::gatherAlias($ex, $aliases);
            }

            return;
        }

        if ($expr !== null) {
            $aliases[] = self::extractAliasFromExpr($expr);
        }
    }

    private static function extractAliasFromExpr(mixed $expr) : string
    {
        if ($expr instanceof Expr\Select) {
            foreach ($expr->getParts() as $part) {
                if ($part instanceof Expr\Func) {
                    throw new InvalidArgumentException('Not supported yet for alias in Expr\Func');
                }

                return $part;
            }
        }

        if ($expr instanceof Expr\Comparison) {
            $leftExpr = $expr->getLeftExpr();
            if (! is_string($leftExpr)) {
                throw new InvalidArgumentException('Not supported yet for alias in ' . get_debug_type($leftExpr));
            }
            list($alias) = explode('.', $leftExpr);

            return $alias;
        }

        if ($expr instanceof Expr\Func) {
            list($alias) = explode('.', $expr->getName());

            return $alias;
        }

        if (is_string($expr)) {
            // LIKE 'a.status = :status'
            // LIKE 'a.status IS NULL'
            // LIKE 'a.status IS NOT NULL'
            // The above and other constructions such as IN clauses are also allowed, so the decision is made based on 'count($splited) >= 3'.
            $splited = preg_split('/\s+/', $expr);

            if (is_array($splited) && count($splited) >= 3) {
                // LIKE a.status
                $leftExpr = $splited[0];
                list($alias) = explode('.', $leftExpr);

                return $alias;
            }
        }

        throw new InvalidArgumentException('Not supported yet for alias in ' . get_debug_type($expr));
    }

    /**
     * Checking JOIN dependencies
     *
     * @param array<string, array<Expr\Join>> $joinParts
     *
     * @return array<string|null, string>
     */
    private static function createJoinDependencyMap(array $joinParts) : array
    {
        $dependencyMap = [];

        foreach ($joinParts as $rootAlias => $joins) {
            foreach ($joins as $join) {
                list($parentAlias) = explode('.', $join->getJoin());

                $dependencyMap[$join->getAlias()] = $parentAlias;
            }
        }

        return $dependencyMap;
    }

    /**
     * Gather all the aliases that require JOIN between the specified alias and the root alias.
     *
     * @param array<string|null, string> $joinDependencyMap
     * @param list<mixed>                &$dependencyAliases
     */
    private static function gatherJoinDependency(string $rootAlias, mixed $alias, array $joinDependencyMap, array &$dependencyAliases) : void
    {
        if (! isset($joinDependencyMap[$alias])) {
            return;
        }

        $dependencyAlias = $joinDependencyMap[$alias];

        if ($dependencyAlias === $rootAlias) {
            return;
        }

        $dependencyAliases[] = $dependencyAlias;

        self::gatherJoinDependency($rootAlias, $dependencyAlias, $joinDependencyMap, $dependencyAliases);
    }
}

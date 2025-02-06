<?php

namespace Kalibora\ChunkGenerator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class QueryOptimizerTest extends TestCase
{
    /**
     * @dataProvider provideOptimizeForMax
     */
    public function testOptimizeForMax(callable $qbModifier, string $expectedDql)
    {
        $em = self::getMockBuilder(EntityManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $qb = new QueryBuilder($em);
        $qbModifier($qb);

        $optimizer = new QueryOptimizer();
        $qbForMax = $optimizer->optimizeForMax($qb);

        $this->assertSame($expectedDql, $qbForMax->getDQL());
    }

    public static function provideOptimizeForMax() : iterable
    {
        yield 'No JOIN, No WHERE' => [
            function (QueryBuilder $qb) {
                $qb->select('u')->from('User', 'u');
            },
            'SELECT u FROM User u',
        ];

        yield 'No JOIN, WHERE is only the main table' => [
            function (QueryBuilder $qb) {
                $qb->select('u')->from('User', 'u')->where('u.name LIKE :username');
            },
            'SELECT u FROM User u WHERE u.name LIKE :username',
        ];

        yield 'JOIN, No WHERE' => [
            function (QueryBuilder $qb) {
                $qb
                    ->select('u, e')
                    ->from('User', 'u')
                    ->join('u.address', 'a')
                    ->join('u.email', 'e', Expr\Join::WITH, 'e.userId = u.id')
                    ->orderBy('u.name')
                ;
            },
            'SELECT u FROM User u',
        ];

        yield 'JOIN, WHERE is only the main table' => [
            function (QueryBuilder $qb) {
                $qb
                    ->select('u, e')
                    ->from('User', 'u')
                    ->join('u.address', 'a')
                    ->join('u.email', 'e', Expr\Join::WITH, 'e.userId = u.id')
                    ->where('u.name LIKE :username')
                    ->orderBy('u.name')
                ;
            },
            'SELECT u FROM User u WHERE u.name LIKE :username',
        ];

        yield 'JOIN, WHERE has conditions for related tables' => [
            function (QueryBuilder $qb) {
                $qb
                    ->select('u, e')
                    ->from('User', 'u')
                    ->join('u.address', 'a')
                    ->join('u.email', 'e', Expr\Join::WITH, 'e.userId = u.id')
                    ->where('u.name LIKE :username')
                    ->andWhere('e.email LIKE :email')
                    ->orderBy('u.name')
                ;
            },
            'SELECT u FROM User u INNER JOIN u.email e WITH e.userId = u.id WHERE u.name LIKE :username AND e.email LIKE :email',
        ];
    }
}

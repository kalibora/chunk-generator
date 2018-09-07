# chunk-generator

Chunk generator (For keep memory usage low)

## Installation

```
composer require kalibora/chunk-generator
```

## Usage

### For doctrine

```php
use Kalibora\ChunkGenerator\ChunkGenerator;

$fooRepository = $manager->getRepository(Foo::class);
$maxId = (int) $fooRepository->createQueryBuilder('f')
    ->select('MAX(f.id)')
    ->getQuery()
    ->getSingleScalarResult()
;

$gen = ChunkGenerator::builder()
    ->setChunkSize(200)
    ->setMax($maxId)
    ->setFindChunk(function ($start, $end) use ($fooRepository) {
        return $fooRepository->createQueryBuilder('f')
            ->where('f.id BETWEEN :start AND :end')
            ->orderBy('f.id', 'ASC')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->iterate()
        ;
    })
    ->onAfterChunk(function () use ($manager) {
        $manager->clear()
    })
    ->build()
;

foreach ($gen() as $row) {
    $foo = $row[0];
}
```

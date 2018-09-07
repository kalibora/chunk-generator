# chunk-generator

Chunk generator (For keep memory usage low)

## Installation

```
composer require kalibora/chunk-generator
```

## Usage

### For doctrine

```php
use Kalibora\ChunkGenerator\ChunkGeneratorBuilder;

$fooRepository = $manager->getRepository(Foo::class);
$qb = $fooRepository->createQueryBuilder('f');
$gen = ChunkGeneratorBuilder::fromDoctrineQueryBuilder($qb)->setChunkSize(200)->build();

// Iterate all foo entity. Fetch 200 records at a time and clear it.
foreach ($gen() as $foo) {
    echo $foo->getVar(), PHP_EOL;
}
```

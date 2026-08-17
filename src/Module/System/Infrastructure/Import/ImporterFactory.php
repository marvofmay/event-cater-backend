<?php

declare(strict_types=1);

namespace App\Module\System\Infrastructure\Import;

use App\Common\Domain\Interface\XLSXIteratorInterface;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ImporterFactory
{
    private array $importers;

    public function __construct(#[AutowireIterator(tag: 'app.importer')] private readonly iterable $taggedImporters)
    {
        $this->importers = [];

        foreach ($this->taggedImporters as $importer) {
            $this->importers[$importer->getType()] = $importer;
        }
    }

    public function getImporter(ImportKindEnum $type): XLSXIteratorInterface
    {
        $importer = $this->importers[$type->value] ?? null;
        if (!$importer) {
            throw new \InvalidArgumentException("Importer not found for type: {$type->value}");
        }

        return $importer;
    }
}

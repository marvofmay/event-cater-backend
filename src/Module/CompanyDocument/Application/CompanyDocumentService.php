<?php

declare(strict_types=1);

namespace App\Module\CompanyDocument\Application;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\CompanyDocument\Domain\Entity\CompanyDocument;
use App\Module\CompanyDocument\Infrastructure\Storage\CompanyDocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class CompanyDocumentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CompanyDocumentStorage $storage,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $category = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('d')
            ->from(CompanyDocument::class, 'd')
            ->orderBy('d.category', 'ASC')
            ->addOrderBy('d.title', 'ASC');
        if ($category !== null && $category !== '' && $category !== 'all') {
            $qb->andWhere('d.category = :category')->setParameter('category', $category);
        }
        $items = $qb->getQuery()->getResult();

        return array_map(static fn (CompanyDocument $document) => $document->toArray(), $items);
    }

    public function get(string $uuid): CompanyDocument
    {
        $document = $this->em->find(CompanyDocument::class, $uuid);
        if (!$document instanceof CompanyDocument) {
            throw new PlanningResourceNotFound('Nie znaleziono dokumentu.');
        }

        return $document;
    }

    public function create(string $title, ?string $description, string $category, UploadedFile $file, string $userUuid, string $userName): array
    {
        [$title, $description, $category] = $this->validate($title, $description, $category);
        $meta = $this->storage->store($file);
        $document = new CompanyDocument(
            $title,
            $description,
            $category,
            $meta['storedName'],
            $meta['fileName'],
            $meta['mimeType'],
            $meta['size'],
            $userUuid,
            $userName,
        );
        $this->em->persist($document);
        $this->em->flush();

        return $document->toArray();
    }

    public function update(string $uuid, string $title, ?string $description, string $category, ?UploadedFile $file): array
    {
        [$title, $description, $category] = $this->validate($title, $description, $category);
        $document = $this->get($uuid);
        $document->replaceMeta($title, $description, $category);
        if ($file !== null) {
            $old = $document->storedName();
            $meta = $this->storage->store($file);
            $document->replaceFile($meta['storedName'], $meta['fileName'], $meta['mimeType'], $meta['size']);
            $this->storage->discard($old);
        }
        $this->em->flush();

        return $document->toArray();
    }

    public function delete(string $uuid): void
    {
        $document = $this->get($uuid);
        $this->storage->discard($document->storedName());
        $this->em->remove($document);
        $this->em->flush();
    }

    /** @return array{0: string, 1: ?string, 2: string} */
    private function validate(string $title, ?string $description, string $category): array
    {
        $title = trim($title);
        $description = $description !== null ? trim($description) : null;
        if ($description === '') {
            $description = null;
        }
        if ($title === '' || mb_strlen($title) > 200) {
            throw new BusinessRuleViolation('Tytuł jest wymagany (max 200 znaków).');
        }
        if ($description !== null && mb_strlen($description) > 5000) {
            throw new BusinessRuleViolation('Opis może mieć max 5000 znaków.');
        }
        if (!in_array($category, CompanyDocument::CATEGORIES, true)) {
            throw new BusinessRuleViolation('Nieznana kategoria dokumentu.');
        }

        return [$title, $description, $category];
    }
}

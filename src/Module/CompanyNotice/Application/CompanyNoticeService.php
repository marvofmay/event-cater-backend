<?php

declare(strict_types=1);

namespace App\Module\CompanyNotice\Application;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\CompanyNotice\Domain\Entity\CompanyNotice;
use App\Module\CompanyNotice\Domain\Entity\CompanyNoticeImage;
use App\Module\CompanyNotice\Infrastructure\Storage\CompanyNoticeMediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class CompanyNoticeService
{
    private const int MAX_GALLERY = 8;
    private const int TITLE_MAX = 200;
    private const int BODY_MAX = 20000;

    public function __construct(
        private EntityManagerInterface $em,
        private CompanyNoticeMediaStorage $storage,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $items = $this->em->createQueryBuilder()
            ->select('n', 'i')
            ->from(CompanyNotice::class, 'n')
            ->leftJoin('n.images', 'i')
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (CompanyNotice $notice) => $notice->toArray(), $items);
    }

    public function get(string $uuid): array
    {
        return $this->require($uuid)->toArray();
    }

    public function image(string $noticeUuid, string $imageUuid): CompanyNoticeImage
    {
        $notice = $this->require($noticeUuid);
        foreach ($notice->images() as $image) {
            if ($image->uuid() === $imageUuid) {
                return $image;
            }
        }
        throw new PlanningResourceNotFound('Nie znaleziono zdjęcia ogłoszenia.');
    }

    /**
     * @param list<UploadedFile> $gallery
     */
    public function create(string $title, string $body, UploadedFile $cover, array $gallery, string $userUuid, string $userName): array
    {
        [$title, $body] = $this->validateText($title, $body);
        if (count($gallery) > self::MAX_GALLERY) {
            throw new BusinessRuleViolation('Możesz dodać maksymalnie '.self::MAX_GALLERY.' zdjęć dodatkowych.');
        }
        $notice = new CompanyNotice($title, $body, $userUuid, $userName);
        $coverMeta = $this->storage->store($cover);
        $notice->addImage(new CompanyNoticeImage(
            $notice,
            $coverMeta['storedName'],
            $coverMeta['fileName'],
            $coverMeta['mimeType'],
            $coverMeta['size'],
            CompanyNoticeImage::ROLE_COVER,
            0,
        ));
        $order = 1;
        foreach ($gallery as $file) {
            $meta = $this->storage->store($file);
            $notice->addImage(new CompanyNoticeImage(
                $notice,
                $meta['storedName'],
                $meta['fileName'],
                $meta['mimeType'],
                $meta['size'],
                CompanyNoticeImage::ROLE_GALLERY,
                $order++,
            ));
        }
        $this->em->persist($notice);
        $this->em->flush();

        return $notice->toArray();
    }

    /**
     * @param list<UploadedFile> $gallery
     * @param list<string> $removeImageUuids
     */
    public function update(string $uuid, string $title, string $body, ?UploadedFile $cover, array $gallery, array $removeImageUuids): array
    {
        [$title, $body] = $this->validateText($title, $body);
        $notice = $this->require($uuid);
        $notice->update($title, $body);

        foreach ($removeImageUuids as $imageUuid) {
            foreach ($notice->images() as $image) {
                if ($image->uuid() !== $imageUuid) {
                    continue;
                }
                if ($image->isCover()) {
                    throw new BusinessRuleViolation('Nie można usunąć zdjęcia wiodącego bez wskazania nowego. Prześlij nowe zdjęcie wiodące.');
                }
                $this->storage->discard($image->storedName());
                $notice->removeImage($image);
            }
        }

        if ($cover !== null) {
            $oldCover = $notice->cover();
            if ($oldCover !== null) {
                $this->storage->discard($oldCover->storedName());
                $notice->removeImage($oldCover);
            }
            $meta = $this->storage->store($cover);
            $notice->addImage(new CompanyNoticeImage(
                $notice,
                $meta['storedName'],
                $meta['fileName'],
                $meta['mimeType'],
                $meta['size'],
                CompanyNoticeImage::ROLE_COVER,
                0,
            ));
        }

        if ($notice->cover() === null) {
            throw new BusinessRuleViolation('Ogłoszenie musi mieć zdjęcie wiodące.');
        }

        $currentGallery = count($notice->gallery());
        if ($currentGallery + count($gallery) > self::MAX_GALLERY) {
            throw new BusinessRuleViolation('Możesz dodać maksymalnie '.self::MAX_GALLERY.' zdjęć dodatkowych.');
        }
        $order = $currentGallery + 1;
        foreach ($gallery as $file) {
            $meta = $this->storage->store($file);
            $notice->addImage(new CompanyNoticeImage(
                $notice,
                $meta['storedName'],
                $meta['fileName'],
                $meta['mimeType'],
                $meta['size'],
                CompanyNoticeImage::ROLE_GALLERY,
                $order++,
            ));
        }

        $this->em->flush();

        return $notice->toArray();
    }

    public function delete(string $uuid): void
    {
        $notice = $this->require($uuid);
        foreach ($notice->images() as $image) {
            $this->storage->discard($image->storedName());
        }
        $this->em->remove($notice);
        $this->em->flush();
    }

    private function require(string $uuid): CompanyNotice
    {
        $notice = $this->em->find(CompanyNotice::class, $uuid);
        if (!$notice instanceof CompanyNotice) {
            throw new PlanningResourceNotFound('Nie znaleziono ogłoszenia.');
        }

        return $notice;
    }

    /** @return array{0: string, 1: string} */
    private function validateText(string $title, string $body): array
    {
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || mb_strlen($title) > self::TITLE_MAX) {
            throw new BusinessRuleViolation('Tytuł jest wymagany (max '.self::TITLE_MAX.' znaków).');
        }
        if ($body === '' || mb_strlen($body) > self::BODY_MAX) {
            throw new BusinessRuleViolation('Treść jest wymagana (max '.self::BODY_MAX.' znaków).');
        }

        return [$title, $body];
    }
}

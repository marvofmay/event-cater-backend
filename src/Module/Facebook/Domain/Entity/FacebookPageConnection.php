<?php

declare(strict_types=1);

namespace App\Module\Facebook\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'facebook_page_connection')]
class FacebookPageConnection
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid = '00000000-0000-4000-8000-000000000001';

    #[ORM\Column(length: 64)]
    private string $pageId = '';

    #[ORM\Column(length: 250)]
    private string $pageName = '';

    #[ORM\Column(type: 'text')]
    private string $pageAccessToken = '';

    #[ORM\Column(length: 36)]
    private string $connectedByUserUuid = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $connectedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->connectedAt = $now;
        $this->updatedAt = $now;
    }

    public function replace(string $pageId, string $pageName, string $encryptedToken, string $userUuid): void
    {
        $this->pageId = $pageId;
        $this->pageName = $pageName;
        $this->pageAccessToken = $encryptedToken;
        $this->connectedByUserUuid = $userUuid;
        $this->connectedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = $this->connectedAt;
    }

    public function pageId(): string
    {
        return $this->pageId;
    }

    public function pageName(): string
    {
        return $this->pageName;
    }

    public function encryptedToken(): string
    {
        return $this->pageAccessToken;
    }

    public function toPublicArray(): array
    {
        return [
            'connected' => $this->pageId !== '',
            'pageId' => $this->pageId !== '' ? $this->pageId : null,
            'pageName' => $this->pageName !== '' ? $this->pageName : null,
            'connectedAt' => $this->pageId !== '' ? $this->connectedAt->format(\DATE_ATOM) : null,
        ];
    }
}

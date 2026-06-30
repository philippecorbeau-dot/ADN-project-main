<?php

namespace App\Event\User;

use Symfony\Contracts\EventDispatcher\Event;
use App\Entity\User\KycDocument;
use App\Entity\User\User;

class DocumentEvent extends Event
{
    public function __construct(
        private KycDocument $document,
        private User $user,
        private string $type
    ) {
    }

    public function getDocument(): KycDocument
    {
        return $this->document;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getType(): string
    {
        return $this->type;
    }
}

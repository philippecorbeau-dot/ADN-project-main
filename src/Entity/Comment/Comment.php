<?php

namespace App\Entity\Comment;

use App\Repository\Comment\CommentRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User\User;


#[ORM\Table(name: 'comment')]
#[ORM\Entity(repositoryClass: CommentRepository::class)]

class Comment
{
    use Fields;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\User\User', inversedBy: 'comments')]
    private $user;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private $createdAt;

    #[ORM\Column(name: 'username', type: 'string')]
    private $username;

    #[ORM\Column(name: 'email', type: 'string')]
    private $email;

    #[ORM\Column(name: 'content', type: 'text')]
    private $content;

    #[ORM\Column(name: 'model', type: 'string')]
    private $model;

    #[ORM\Column(name: 'model_id', type: 'integer')]
    private $modelId;

    #[ORM\Column(name: 'parent_id', type: 'integer')]
    private $parentId;


    public function getId()
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): Comment
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): Comment
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): Comment
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): Comment
    {
        $this->email = $email;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): Comment
    {
        $this->content = $content;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): Comment
    {
        $this->model = $model;

        return $this;
    }

    public function getModelId(): int
    {
        return $this->modelId;
    }

    public function setModelId(int $modelId): Comment
    {
        $this->modelId = $modelId;

        return $this;
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function setParentId(int $parentId): Comment
    {
        $this->parentId = $parentId;

        return $this;
    }
}

<?php

namespace App\Entity\Rating;

use App\Entity\Blog\Post as BlogPost;
use App\Entity\Cocoon\Post as CocoonPost;
use App\Repository\Rating\RatingRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: RatingRepository::class)]
#[ORM\HasLifecycleCallbacks]

class Rating
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private $updatedAt;

    #[ORM\Column(type: 'integer')]
    private $score;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $ip;

    #[ORM\ManyToOne(targetEntity: BlogPost::class, inversedBy: 'ratings')]
    private $blogPost;

    #[ORM\ManyToOne(targetEntity: CocoonPost::class, inversedBy: 'ratings')]
    private $cocoonPost;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getBlogPost(): ?BlogPost
    {
        return $this->blogPost;
    }

    public function setBlogPost(?BlogPost $blogPost): self
    {
        $this->blogPost = $blogPost;

        return $this;
    }

    public function getCocoonPost(): ?CocoonPost
    {
        return $this->cocoonPost;
    }

    public function setCocoonPost(?CocoonPost $cocoonPost): self
    {
        $this->cocoonPost = $cocoonPost;

        return $this;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updatedTimestamps(): void
    {
        $this->setUpdatedAt(new \DateTimeImmutable('now'));

        if ($this->getCreatedAt() == null)
        {
            $this->setCreatedAt(new \DateTimeImmutable('now'));
        }
    }

    public function setUpdatedAt($updatedAt): Rating
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}

<?php

namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;


// Entité désactivée (suppression progressive)
// #[ORM\Table(name: 'user_marketing')]
// #[ORM\HasLifecycleCallbacks]
// #[ORM\Entity]

class Marketing
{

    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private $updatedAt;

    #[ORM\Column(name: 'utm_source', type: 'string', length: 55)]
    private $utmSource;

    #[ORM\Column(name: 'utm_medium', type: 'string', length: 55)]
    private $utmMedium;

    #[ORM\Column(name: 'utm_campaign', type: 'string', length: 55, nullable: true)]
    private $utmCampaign;

    #[ORM\Column(name: 'utm_content', type: 'string', length: 55, nullable: true)]
    private $utmContent;

    #[ORM\OneToOne(targetEntity: 'App\Entity\User\User', inversedBy: 'marketing')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private $user;

    public function getId()
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): Marketing
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): Marketing
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUtmSource()
    {
        return $this->utmSource;
    }

    public function setUtmSource($utmSource): void
    {
        $this->utmSource = $utmSource;
    }

    public function getUtmMedium()
    {
        return $this->utmMedium;
    }

    public function setUtmMedium($utmMedium): Marketing
    {
        $this->utmMedium = $utmMedium;
        return $this;
    }

    public function getUtmCampaign()
    {
        return $this->utmCampaign;
    }

    public function setUtmCampaign($utmCampaign): Marketing
    {
        $this->utmCampaign = $utmCampaign;
        return $this;
    }

    public function getUtmContent()
    {
        return $this->utmContent;
    }

    public function setUtmContent($utmContent): Marketing
    {
        $this->utmContent = $utmContent;
        return $this;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser($user): void
    {
        $this->user = $user;
    }


    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updatedTimestamps()
    {
        $this->setUpdatedAt(new \DateTime('now'));

        if ($this->getCreatedAt() == null) {
            $this->setCreatedAt(new \DateTime('now'));
        }
    }

}

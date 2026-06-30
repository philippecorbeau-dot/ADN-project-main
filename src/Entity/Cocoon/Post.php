<?php

namespace App\Entity\Cocoon;

use App\Entity\Rating\Rating;
use App\Repository\Cocoon\PostRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;


#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'cocoon_post')]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
#[UniqueEntity(
    fields: ['landingToOverride'],
    message: 'Un autre article utilise cette page'
)]

class Post
{

    public const CATEGORY_ID_CROWDFUNING = 'crowdfunding';
    public const CATEGORY_ID_LMNP = 'lmnp';
    public const CATEGORY_ID_SCPI = 'scpi';
    public const CATEGORY_ID_LIFE_INSURANCE = 'assurance-vie';
    public const CATEGORY_ID_LOCATIF = 'investissement-locatif';
    public const CATEGORY_ID_COMPARATIVE = 'comparatif';
    public const CATEGORY_ID_OTHER = 'autre';
    
    public const RIGHT_TITLE_CROWDFUNDING = "Tout savoir sur le Crowdfunding";
    public const RIGHT_TITLE_LMNP = "Tout savoir sur le LMNP";
    public const RIGHT_TITLE_SCPI = "Tout savoir sur la SCPI";
    public const RIGHT_TITLE_ALL = "Tout savoir sur l'investissement immobilier";
    public const RIGHT_TITLE_VEFA = "Tout savoir sur l'investissement locatif";
    public const RIGHT_TITLE_LI = "Tout savoir sur l'assurance-vie";
    public const RIGHT_TITLE_COMPARATIVE = "Comparatif d'investissement immobilier";

    public const categories = [
        self::CATEGORY_ID_CROWDFUNING => 'Crowdfunding',
        self::CATEGORY_ID_LMNP => 'LMNP',
        self::CATEGORY_ID_SCPI => 'Scpi',
        self::CATEGORY_ID_LOCATIF => 'Investissement locatif',
        self::CATEGORY_ID_LIFE_INSURANCE => 'Assurance Vie',
        self::CATEGORY_ID_COMPARATIVE => 'Comparatif',
        self::CATEGORY_ID_OTHER => 'Autre',
    ];

    public const titles = [
        self::CATEGORY_ID_CROWDFUNING => self::RIGHT_TITLE_CROWDFUNDING,
        self::CATEGORY_ID_LMNP => self::RIGHT_TITLE_LMNP,
        self::CATEGORY_ID_SCPI=> self::RIGHT_TITLE_SCPI,
        self::CATEGORY_ID_LOCATIF => self::RIGHT_TITLE_VEFA,
        self::CATEGORY_ID_LIFE_INSURANCE => self::RIGHT_TITLE_LI,
        self::CATEGORY_ID_COMPARATIVE => self::RIGHT_TITLE_COMPARATIVE,
        self::CATEGORY_ID_OTHER => self::RIGHT_TITLE_ALL,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    protected $id;

    #[ORM\Column(name: 'title', type: 'string', length: 255, unique: true)]
    private $title;

    #[ORM\Column(name: 'content', type: 'text')]
    private $content;

    #[ORM\Column(name: 'status', type: 'integer')]
    private $status;


    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private $updatedAt;

    #[ORM\Column(name: 'seo_title', type: 'string', length: 255)]
    private $seoTitle;

    #[ORM\Column(name: 'seo_description', type: 'string', length: 255)]
    private $seoDescription;

    #[ORM\Column(name: 'seo_slug', type: 'string', length: 255, unique: true)]
    private $seoSlug;

    #[ORM\ManyToOne(targetEntity: 'Post', inversedBy: 'childrens')]
    #[ORM\JoinColumn(referencedColumnName: 'id', nullable: true)]
    private $parent;

    #[ORM\OneToMany(targetEntity: 'Post', mappedBy: 'parent')]
    #[ORM\JoinColumn(referencedColumnName: 'id', nullable: true)]
    private $childrens;

    #[ORM\Column(name: 'category_id', type: 'string', length: 155)]
    private $categoryId;

    #[Vich\UploadableField(mapping: 'blog_image', fileNameProperty: 'imageName')]
    private $imageFile;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $imageName;

    #[ORM\Column(type: 'string', length: 255, unique: true, nullable: true)]
    private $landingToOverride;

    #[ORM\OneToMany(targetEntity: Rating::class, mappedBy: 'cocoonPost')]
    private $ratings;

    #[ORM\Column(name: 'image_alt', type: 'string', length: 255, nullable: true)]
    private $imageAlt;

    public function __toString(): string
    {
        return (string) $this->getTitle();
    }
    
    public function __construct()
    {
        $this->childrens = new ArrayCollection();
        $this->ratings = new ArrayCollection();
    }
    
    public function getId()
    {
        return $this->id;
    }

    public function setTitle($title): Post
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setContent($content): Post
    {
        $this->content = $content;
        return $this;
    }

    public function setStatus($status): Post
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setCreatedAt($createdAt): Post
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setUpdatedAt($updatedAt): Post
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function setSeoTitle($seoTitle): Post
    {
        $this->seoTitle = $seoTitle;
        return $this;
    }

    public function getSeoTitle()
    {
        return $this->seoTitle;
    }

    public function setSeoDescription($seoDescription): Post
    {
        $this->seoDescription = $seoDescription;
        return $this;
    }

    public function getSeoDescription()
    {
        return $this->seoDescription;
    }

    public function setSeoSlug($seoSlug): Post
    {
        $this->seoSlug = $seoSlug;
        return $this;
    }

    public function getSeoSlug()
    {
        return $this->seoSlug;
    }

    public function setParent($parent): Post
    {
        $this->parent = $parent;
        return $this;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function setCategoryId($categoryId)
    {
        $this->categoryId = $categoryId;
        return $this;
    }

    public function getCategoryId()
    {
        return $this->categoryId;
    }
    
    public function getCategories(): array
    {
        return self::categories;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updatedTimestamps(): void
    {
        $this->setUpdatedAt(new DateTime('now'));

        if ($this->getCreatedAt() == null)
        {
            $this->setCreatedAt(new DateTime('now'));
        }
    }
    
    public function getCategoryName(): string
    {
        return self::categories[$this->categoryId];
    }

    public function addChildrens(Post $children): Post
    {
        $this->childrens[] = $children;

        return $this;
    }

    public function removeChildren(Post $children): void
    {
        $this->childrens->removeElement($children);
    }

    public function getChildrens()
    {
        return $this->childrens;
    }

    public function setImageFile(File $image = NULL): Post
    {
        $this->imageFile = $image;

        if($image) {
            $this->updatedAt = new DateTime('now');
        }

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageName(?string $imageName): Post
    {
        $this->imageName = $imageName;

        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }
    
    public function getPostRightTitle(): string
    {
        return self::titles[$this->categoryId];
    }

    public function setLandingToOverride($landingToOverride)
    {
        $this->landingToOverride = $landingToOverride;
        return $this;
    }

    public function getLandingToOverride()
    {
        return $this->landingToOverride;
    }

    /**
     * @return Collection<int, Rating>
     */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    public function addRating(Rating $rating): self
    {
        if (!$this->ratings->contains($rating)) {
            $this->ratings[] = $rating;
            $rating->setCocoonPost($this);
        }

        return $this;
    }

    public function removeRating(Rating $rating): self
    {
        if ($this->ratings->removeElement($rating)) {
            // set the owning side to null (unless already changed)
            if ($rating->getPostCocoon() === $this) {
                $rating->setCocoonPost(null);
            }
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getImageAlt()
    {
        return $this->imageAlt;
    }

    /**
     * @param $imageAlt
     * @return $this
     */
    public function setImageAlt($imageAlt): self
    {
        $this->imageAlt = $imageAlt;
        return $this;
    }
}

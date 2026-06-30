<?php

namespace App\Entity\Blog;

use App\Entity\Blog\Fields\PostFields;
use App\Entity\Rating\Rating;
use App\Repository\Blog\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use App\Entity\User\User;


#[ORM\Table(name: 'blog_post')]
#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
#[\AllowDynamicProperties]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]

class Post
{
    use PostFields;
    use SoftDeleteableEntity;

    const SITEMAP_LIMIT_PER_PAGES = 13;
    const STATUS_LIST = [
        2 => 'En cours de moderation',
        1 => 'Active',
        0 => 'Desactive',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: "App\Entity\User\User", cascade: ['persist'], inversedBy: 'posts')]
    private $user;

    #[ORM\ManyToOne(targetEntity: 'Category', cascade: ['persist'], inversedBy: 'posts')]
    private $category;

    #[Vich\UploadableField(mapping: 'blog_image', fileNameProperty: 'imageName')]
    private $imageFile;

    #[ORM\Column(type: 'string', length: 255)]
    private $imageName;

    #[ORM\Column(name: 'image_alt', type: 'string', length: 255, nullable: true)]
    private $imageAlt;

    #[ORM\Column(name: 'title', type: 'string', length: 255, unique: true)]
    private $title;

    #[ORM\Column(name: 'content', type: 'text')]
    private $content;

    #[ORM\Column(name: 'comments_enabled', type: 'boolean')]
    private $commentsEnabled;

    #[ORM\Column(name: 'publication_date_start', type: 'datetime')]
    private $publicationDateStart;

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

    #[Gedmo\Slug(fields: ['title'], updatable: false)]
    #[ORM\Column(name: 'seo_slug', type: 'string', length: 255, unique: true)]
    private $seoSlug;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $canonicalUrl;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $redirectUrl;

    #[ORM\Column(name: 'related_posts', type: 'json', nullable: true)]
    private $relatedPosts;

    #[ORM\OneToMany(targetEntity: Rating::class, mappedBy: 'blogPost')]
    private $ratings;

    #[ORM\Column(name: 'disable_in_sitemap', type: 'boolean', options: ['default' => 0])]
    private $disableInSitemap = 0;

    #[ORM\Column(name: 'is_featured', type: 'boolean', options: ['default' => 0])]
    private bool $isFeatured = false;

    public function __construct()
    {
        $this->ratings = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->getTitle();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setTitle(string $title): Post
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setContent(string $content): Post
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setCommentsEnabled(bool $commentsEnabled): Post
    {
        $this->commentsEnabled = $commentsEnabled;

        return $this;
    }

    public function getCommentsEnabled(): ?bool
    {
        return $this->commentsEnabled;
    }

    public function setPublicationDateStart(\DateTime $publicationDateStart): Post
    {
        $this->publicationDateStart = $publicationDateStart;

        return $this;
    }

    public function getPublicationDateStart(): ?\DateTime
    {
        return $this->publicationDateStart;
    }

    public function setStatus(string $status): Post
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getStatusList(): array
    {
        return self::STATUS_LIST;
    }

    public function getStatusName(): string
    {
        return self::STATUS_LIST[$this->status];
    }

    public function setCreatedAt(\DateTime $createdAt): Post
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        if(!empty($this->publicationDateStart))
        {
            $this->createdAt = $this->publicationDateStart;
        }

        return $this->createdAt;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updatedTimestamps(): void
    {
        $this->setUpdatedAt(new \DateTime('now'));

        if ($this->getCreatedAt() == null)
        {
            $this->setCreatedAt(new \DateTime('now'));
        }
    }

    public function setUpdatedAt(\DateTime $updatedAt): Post
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        if ($this->updatedAt === null) {
            return $this->getCreatedAt() ?? new \DateTime('now');
        }
        return $this->updatedAt;
    }

    public function setUser(User $user = null): Post
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setSeoTitle(string $seoTitle): Post
    {
        $this->seoTitle = $seoTitle;

        return $this;
    }


    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function setSeoDescription(string $seoDescription): Post
    {
        $this->seoDescription = $seoDescription;

        return $this;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function setSeoSlug($seoSlug): Post
    {
        $this->seoSlug = $seoSlug;

        return $this;
    }

    public function getSeoSlug(): ?string
    {
        return $this->seoSlug;
    }

    public function setImageFile(File $image = NULL): Post
    {
        $this->imageFile = $image;

        if ($image)
        {
            $this->updatedAt = new \DateTime('now');
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

    public function setCategory(Category $category = null): Post
    {
        $this->category = $category;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    /**
     * This method returns feed item title.
     */
    public function getFeedItemTitle(): string
    {
        return $this->getTitle();
    }

    /**
     * This method returns feed item description (or content).
     */
    public function getFeedItemDescription(): string
    {
        return $this->getSeoDescription();
    }

    /**
     * This method returns the name of the route.
     */
    public function getFeedItemRouteName(): string
    {
        return 'blog_post';
    }

    /**
     * This method returns the parameters for the route.
     */
    public function getFeedItemRouteParameters(): array
    {
        return [
            'category_slug' => $this->getCategory()->getSeoSlug(),
            'post_slug' => $this->getSeoSlug()
        ];
    }

    /**
     * This method returns the anchor to be appended on this item's url.
     * @return string The anchor, without the "#"
     */
    public function getFeedItemUrlAnchor(): string
    {
        return '';
    }

    /**
     * This method returns item publication date.
     */
    public function getFeedItemPubDate(): \DateTime
    {
        return $this->getCreatedAt();
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function setCanonicalUrl(?string $canonicalUrl): self
    {
        $this->canonicalUrl = $canonicalUrl;

        return $this;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    public function setRedirectUrl(?string $redirectUrl): self
    {
        $this->redirectUrl = $redirectUrl;

        return $this;
    }

    public function getRelatedPosts(): ?array
    {
        $arrayRelatedPosts = array_filter($this->relatedPosts, function($element) {
            return !is_array($element);
        });

        return $arrayRelatedPosts;
    }

    public function setRelatedPosts($relatedPosts): Post
    {
        $this->relatedPosts[] = $relatedPosts;

        return $this;
    }

    public function addRelatedPosts($relatedPosts): Post
    {
        $this->relatedPosts = $relatedPosts;

        return $this;
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
            $rating->setBlogPost($this);
        }

        return $this;
    }

    public function removeRating(Rating $rating): self
    {
        if ($this->ratings->removeElement($rating)) {
            // set the owning side to null (unless already changed)
            if ($rating->getBlogPost() === $this) {
                $rating->setBlogPost(null);
            }
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getImageAlt(): ?string
    {
        return $this->imageAlt;
    }

    /**
     * @param mixed $imageAlt
     * @return Post
     */
    public function setImageAlt(?string $imageAlt): self
    {
        $this->imageAlt = $imageAlt;
        return $this;
    }

    /**
     * @param bool $disableInSitemap
     * @return Post
     */
    public function setDisableInSitemap(bool $disableInSitemap): self
    {
        $this->disableInSitemap = $disableInSitemap;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDisableInSitemap(): bool
    {
        return $this->disableInSitemap;
    }

    /**
     * @return bool
     */
    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    /**
     * @param bool $isFeatured
     * @return self
     */
    public function setIsFeatured(bool $isFeatured): self
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }
}
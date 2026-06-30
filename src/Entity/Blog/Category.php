<?php

namespace App\Entity\Blog;

use App\Entity\Blog\Fields\CategoryFields;
use App\Repository\Blog\CategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


#[ORM\Table(name: 'blog_category')]
#[ORM\Entity(repositoryClass: CategoryRepository::class)]

class Category
{

    use CategoryFields;

    const CAT_IMMOBILIER = 'immobilier';
    const CAT_FISCALITE = 'fiscalite';
    const CAT_CROWDFUNDING = 'crowdfunding';
    const CAT_INVESTISSEMENT = 'investissement';
    const CAT_NEWS = 'les-news-d-homunity';
    const CAT_VEFA = 'investissement-locatif';
    const CAT_SCPI = 'scpi';
    const CAT_LIFEINSURANCE = 'assurance-vie';


    #[ORM\OneToMany(targetEntity: 'Post', mappedBy: 'category')]
    #[ORM\OrderBy(['publicationDateStart' => 'DESC' ])]
    private $posts;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\Column(name: 'name', type: 'string', length: 255, unique: true)]
    private $name;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(name: 'seo_title', type: 'string', length: 255, nullable: true)]
    private $seoTitle;

    #[ORM\Column(name: 'seo_description', type: 'string', length: 255, nullable: true)]
    private $seoDescription;

    #[Gedmo\Slug(fields: ['name'])]
    #[ORM\Column(name: 'seo_slug', type: 'string', length: 255, unique: true)]
    private $seoSlug;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->posts = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setName(string $name): Category
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setDescription(string $description): Category
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setSeoTitle(string $seoTitle): Category
    {
        $this->seoTitle = $seoTitle;

        return $this;
    }

    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function setSeoDescription(string $seoDescription): Category
    {
        $this->seoDescription = $seoDescription;

        return $this;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function addPost(Post $post): Category
    {
        $this->posts[] = $post;

        return $this;
    }

    public function removePost(Post $post): void
    {
        $this->posts->removeElement($post);
    }

    public function getPosts(): array
    {
        return $this->posts;
    }

    public function setSeoSlug(string $seoSlug): Category
    {
        $this->seoSlug = $seoSlug;

        return $this;
    }

    public function getSeoSlug(): string
    {
        return $this->seoSlug;
    }
    
    public function isImmobilier()
    {
        return self::CAT_IMMOBILIER == $this->seoSlug;
    }

    public function isInvestissement()
    {
        return self::CAT_INVESTISSEMENT == $this->seoSlug;
    }

    public function isCrowdfunding()
    {
        return self::CAT_CROWDFUNDING == $this->seoSlug;
    }

    public function isFiscalite()
    {
        return self::CAT_FISCALITE == $this->seoSlug;
    }

    public function isNews()
    {
        return self::CAT_NEWS == $this->seoSlug;
    }

    public function isVefa()
    {
        return self::CAT_VEFA == $this->seoSlug;
    }
    
    public function isScpi()
    {
        return self::CAT_SCPI == $this->seoSlug;
    }

    public function isLifeInsurance()
    {
        return self::CAT_LIFEINSURANCE == $this->seoSlug;
    }
}

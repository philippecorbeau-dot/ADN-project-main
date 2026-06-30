<?php

namespace App\Entity\User\Knowledge;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_knowledge_financial_products')]
class FinancialProductsKnowledge
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'question_1', type: 'string', length: 20, nullable: true)]
    #[Assert\Choice(choices: ['true', 'false', 'unknown'])]
    private ?string $question1 = null;

    #[ORM\Column(name: 'question_2', type: 'string', length: 20, nullable: true)]
    #[Assert\Choice(choices: ['true', 'false', 'unknown'])]
    private ?string $question2 = null;

    #[ORM\Column(name: 'question_3', type: 'string', length: 20, nullable: true)]
    #[Assert\Choice(choices: ['true', 'false', 'unknown'])]
    private ?string $question3 = null;

    #[ORM\Column(name: 'question_4', type: 'string', length: 20, nullable: true)]
    #[Assert\Choice(choices: ['true', 'false', 'unknown'])]
    private ?string $question4 = null;

    #[ORM\Column(name: 'question_5', type: 'string', length: 20, nullable: true)]
    #[Assert\Choice(choices: ['true', 'false', 'unknown'])]
    private ?string $question5 = null;

    #[ORM\Column(name: 'question_6', type: 'string', length: 20, nullable: true)]
    #[Assert\Choice(choices: ['true', 'false', 'unknown'])]
    private ?string $question6 = null;

    #[ORM\Column(name: 'question_7', type: 'string', length: 20, nullable: true)]
    #[Assert\Choice(choices: ['true', 'false', 'unknown'])]
    private ?string $question7 = null;

    #[ORM\Column(name: 'question_8', type: 'string', length: 20, nullable: true)]
    #[Assert\Choice(choices: ['true', 'false', 'unknown'])]
    private ?string $question8 = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion1(): ?string
    {
        return $this->question1;
    }

    public function setQuestion1(?string $question1): self
    {
        $this->question1 = $question1;
        return $this;
    }

    public function getQuestion2(): ?string
    {
        return $this->question2;
    }

    public function setQuestion2(?string $question2): self
    {
        $this->question2 = $question2;
        return $this;
    }

    public function getQuestion3(): ?string
    {
        return $this->question3;
    }

    public function setQuestion3(?string $question3): self
    {
        $this->question3 = $question3;
        return $this;
    }

    public function getQuestion4(): ?string
    {
        return $this->question4;
    }

    public function setQuestion4(?string $question4): self
    {
        $this->question4 = $question4;
        return $this;
    }

    public function getQuestion5(): ?string
    {
        return $this->question5;
    }

    public function setQuestion5(?string $question5): self
    {
        $this->question5 = $question5;
        return $this;
    }

    public function getQuestion6(): ?string
    {
        return $this->question6;
    }

    public function setQuestion6(?string $question6): self
    {
        $this->question6 = $question6;
        return $this;
    }

    public function getQuestion7(): ?string
    {
        return $this->question7;
    }

    public function setQuestion7(?string $question7): self
    {
        $this->question7 = $question7;
        return $this;
    }

    public function getQuestion8(): ?string
    {
        return $this->question8;
    }

    public function setQuestion8(?string $question8): self
    {
        $this->question8 = $question8;
        return $this;
    }

    public function isComplete(): bool
    {
        return $this->question1 !== null && 
               $this->question2 !== null && 
               $this->question3 !== null && 
               $this->question4 !== null && 
               $this->question5 !== null && 
               $this->question6 !== null && 
               $this->question7 !== null && 
               $this->question8 !== null;
    }

    public function getScore(): int
    {
        $score = 0;
        $correctAnswers = [
            'question1' => 'false', // Action = prêt d'argent
            'question2' => 'true',  // Dividende = baisse du cours
            'question3' => 'false', // Obligation pas toujours remboursée
            'question4' => 'false', // AAA = meilleur rendement que B
            'question5' => 'true',  // Actions plus risquées que obligations
            'question6' => 'true',  // ETF reproduit indice
            'question7' => 'true',  // Volatilité = indicateur risque
            'question8' => 'false', // Performance absolue peut être négative
        ];

        foreach ($correctAnswers as $question => $correctAnswer) {
            $getter = 'get' . ucfirst($question);
            if ($this->$getter() === $correctAnswer) {
                $score++;
            }
        }

        return $score;
    }
} 
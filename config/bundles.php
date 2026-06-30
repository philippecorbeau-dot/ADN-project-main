<?php

return [
    // Core
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class   => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class           => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class     => ['all' => true],

    // Doctrine
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class               => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class   => ['all' => true],
    Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class       => ['dev' => true, 'test' => true],

    // Front/UX
    Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => ['all' => true],
    Symfony\UX\StimulusBundle\StimulusBundle::class        => ['all' => true],
    Symfony\UX\Turbo\TurboBundle::class                    => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class      => ['all' => true],

    // Tiers
    Vich\UploaderBundle\VichUploaderBundle::class                   => ['all' => true],
    Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle::class => ['all' => true],
    SymfonyCasts\Bundle\VerifyEmail\SymfonyCastsVerifyEmailBundle::class => ['all' => true],
    Knp\Bundle\PaginatorBundle\KnpPaginatorBundle::class            => ['all' => true],

    // Dev/Test uniquement
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class             => ['dev' => true, 'test' => true],
    Symfony\Bundle\MakerBundle\MakerBundle::class             => ['dev' => true],
];


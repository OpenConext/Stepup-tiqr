<?php

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Surfnet\SamlBundle\SurfnetSamlBundle;
use Surfnet\GsspBundle\SurfnetGsspBundle;
use Surfnet\StepupBundle\SurfnetStepupBundle;
use Symfony\WebpackEncoreBundle\WebpackEncoreBundle;
use OpenConext\MonitorBundle\OpenConextMonitorBundle;
use FriendsOfBehat\SymfonyExtension\Bundle\FriendsOfBehatSymfonyExtensionBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

return [
    FrameworkBundle::class => ['all' => true],
    MonologBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    WebProfilerBundle::class => ['dev' => true, 'test' => true],
    SurfnetSamlBundle::class => ['all' => true],
    SurfnetGsspBundle::class => ['all' => true],
    SurfnetStepupBundle::class => ['all' => true],
    WebpackEncoreBundle::class => ['all' => true],
    OpenConextMonitorBundle::class => ['all' => true],
    FriendsOfBehatSymfonyExtensionBundle::class => ['test' => true],
    TwigExtraBundle::class => ['all' => true],
];

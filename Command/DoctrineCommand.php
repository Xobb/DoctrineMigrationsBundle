<?php

/*
 * This file is part of the Doctrine MigrationsBundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\MigrationsBundle\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Doctrine\Bundle\DoctrineBundle\Command\DoctrineCommand as BaseCommand;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\DBAL\Migrations\Configuration\AbstractFileConfiguration;

/**
 * Base class for Doctrine console commands to extend from.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class DoctrineCommand extends BaseCommand
{
    public static function configureMigrations(ContainerInterface $container, Configuration $configuration, $em)
    {
        if ($container->hasParameter('doctrine_migrations.default_entity_manager')) {
            $configurationPrefix = 'doctrine_migrations.default_entity_manager';
        } elseif ($container->hasParameter('doctrine_migrations.' . $em)) {
            $configurationPrefix = 'doctrine_migrations.' . $em;
        } else {
            if (null === $em) {
                $message = 'There is no doctrine migrations configuration available for the default entity manager';
            } else {
                $message = sprintf(
                    'There is no doctrine migrations configuration available for the %s entity manager',
                    $em
                );
            }
            throw new \InvalidArgumentException($message);
        }

        $containerParameters = $container->getParameter($configurationPrefix);

        $dir = $containerParameters['dir_name'];
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $configuration->setMigrationsNamespace($containerParameters['namespace']);
        $configuration->setMigrationsDirectory($dir);
        $configuration->setName($containerParameters['name']);
        $configuration->setMigrationsTableName($containerParameters['table_name']);
            $pathPlaceholderArray = array('kernel.root_dir', 'kernel.cache_dir', 'kernel.logs_dir');
            foreach ($pathPlaceholderArray as $pathPlaceholder) {
                if ($container->hasParameter($pathPlaceholder) && preg_match('/\%'.$pathPlaceholder.'\%/', $dir)) {
                    $dir = str_replace('%'.$pathPlaceholder.'%', $container->getParameter($pathPlaceholder), $dir);
                }
            }

        $configuration->setMigrationsDirectory($dir);
        if (!$configuration->getMigrationsNamespace()) {
            $configuration->setMigrationsNamespace($container->getParameter('doctrine_migrations.namespace'));
        }
        if (!$configuration->getName()) {
        }
        // For backward compatibility, need use a table from parameters for overwrite the default configuration
        if (!$configuration->getMigrationsTableName() || !($configuration instanceof AbstractFileConfiguration)) {
        }
        // Migrations is not register from configuration loader
        if (!($configuration instanceof AbstractFileConfiguration)) {
            $configuration->registerMigrationsFromDirectory($configuration->getMigrationsDirectory());
        }

        self::injectContainerToMigrations($container, $configuration->getMigrations());
    }

    /**
     * Injects the container to migrations aware of it
     */
    private static function injectContainerToMigrations(ContainerInterface $container, array $versions)
    {
        foreach ($versions as $version) {
            $migration = $version->getMigration();
            if ($migration instanceof ContainerAwareInterface) {
                $migration->setContainer($container);
            }
        }
    }
}

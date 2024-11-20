<?php

declare(strict_types=1);

namespace HawkBundle\DependencyInjection;

use HawkBundle\Catcher;
use HawkBundle\Monolog\Handler;
use HawkBundle\Service\BeforeSendServiceInterface;
use HawkBundle\Transport\GuzzlePromisesTransport;
use Monolog\Logger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class HawkExtension extends Extension
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Load configuration files
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // Set parameters and register services
        $container->setParameter('hawk.integration_token', $config['integration_token']);

        // Register TransportInterface
        $container->register(GuzzlePromisesTransport::class);

        $options = ['integrationToken' => $config['integration_token']];

        if (
            isset($config['before_send_service'])
            && class_exists($config['before_send_service'])
            && is_subclass_of($config['before_send_service'], BeforeSendServiceInterface::class)
        ) {
            $options['beforeSend'] = new Reference($config['before_send_service']);
        }

        // Register Catcher
        $container->register(Catcher::class)
            ->setArgument('$options', $options)
            ->setArgument('$transport', new Reference(GuzzlePromisesTransport::class));

        // Register Monolog\Handler
        $container->register(Handler::class)
            ->setArgument('$catcher', new Reference(Catcher::class))
            ->setArgument('$request', new Reference('request_stack'))
            ->setArgument('$level', Logger::ERROR)
            ->addTag('monolog.handler');
    }
}

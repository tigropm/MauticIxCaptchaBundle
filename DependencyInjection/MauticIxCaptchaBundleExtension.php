<?php

declare(strict_types=1);

namespace MauticPlugin\MauticIxCaptchaBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Symfony DI Extension for MauticIxCaptchaBundle.
 *
 * Handles version-specific constructor arguments for AbstractIntegration:
 *   Mautic 5: __construct(..., SessionInterface, RequestStack, ...) — 16 args
 *   Mautic 6: __construct(..., RequestStack, ...) — 15 args (session removed)
 *   Mautic 7: __construct(..., RequestStack, ..., FieldsWithUniqueIdentifier) — 16 args
 */
class MauticIxCaptchaBundleExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Config')
        );

        $loader->load('config.php');

        $this->configureIntegrationService($container);
    }

    /**
     * Sets the correct constructor arguments for the integration service
     * depending on the Mautic / Symfony version detected at compile time.
     */
    private function configureIntegrationService(ContainerBuilder $container): void
    {
        // Detect Mautic major version via the mautic.version parameter
        // (set in Mautic's core app/config/config.php as 'mautic_version').
        // Fall back to Symfony version if unavailable.
        $mauticVersion = $container->hasParameter('mautic.version')
            ? (string) $container->getParameter('mautic.version')
            : '5.0.0';

        $majorVersion = (int) explode('.', $mauticVersion)[0];

        $definition = $container->getDefinition('mautic.integration.ixcaptcha');

        if ($majorVersion >= 7) {
            // Mautic 7: SessionInterface removed, FieldsWithUniqueIdentifier added
            $definition->setArguments([
                new Reference('event_dispatcher'),
                new Reference('mautic.helper.cache_storage'),
                new Reference('doctrine.orm.entity_manager'),
                new Reference('request_stack'),
                new Reference('router'),
                new Reference('translator'),
                new Reference('logger'),
                new Reference('mautic.helper.encryption'),
                new Reference('mautic.lead.model.lead'),
                new Reference('mautic.lead.model.company'),
                new Reference('mautic.helper.paths'),
                new Reference('mautic.core.model.notification'),
                new Reference('mautic.lead.model.field'),
                new Reference('mautic.plugin.model.integration_entity'),
                new Reference('mautic.lead.model.dnc'),
                new Reference('mautic.lead.field.fields_with_unique_identifier'),
            ]);
        } else {
            // Mautic 5 / 6: SessionInterface still present (5), or ignored (6)
            $definition->setArguments([
                new Reference('event_dispatcher'),
                new Reference('mautic.helper.cache_storage'),
                new Reference('doctrine.orm.entity_manager'),
                new Reference('session'),
                new Reference('request_stack'),
                new Reference('router'),
                new Reference('translator'),
                new Reference('logger'),
                new Reference('mautic.helper.encryption'),
                new Reference('mautic.lead.model.lead'),
                new Reference('mautic.lead.model.company'),
                new Reference('mautic.helper.paths'),
                new Reference('mautic.core.model.notification'),
                new Reference('mautic.lead.model.field'),
                new Reference('mautic.plugin.model.integration_entity'),
                new Reference('mautic.lead.model.dnc'),
            ]);
        }
    }
}

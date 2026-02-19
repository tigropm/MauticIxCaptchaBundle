<?php

declare(strict_types=1);

namespace MauticPlugin\MauticIxCaptchaBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Sets the correct constructor arguments for mautic.integration.ixcaptcha
 * depending on the Mautic major version:
 *
 *   Mautic 5 — SessionInterface present (16 args incl. session)
 *   Mautic 6 — SessionInterface removed (15 args)
 *   Mautic 7 — FieldsWithUniqueIdentifier added (16 args, no session)
 */
class IntegrationArgumentsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mautic.integration.ixcaptcha')) {
            return;
        }

        $definition = $container->getDefinition('mautic.integration.ixcaptcha');

        // Detect version by checking which services exist in the container.
        // This is more reliable than reading a version parameter.
        $hasSession              = $container->hasDefinition('session') || $container->hasAlias('session');
        $hasFieldsWithUniqueId   = $container->hasDefinition('mautic.lead.field.fields_with_unique_identifier');

        if (!$hasSession && $hasFieldsWithUniqueId) {
            // Mautic 7: no session, has FieldsWithUniqueIdentifier
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
        } elseif ($hasSession) {
            // Mautic 5: session still present
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
        } else {
            // Mautic 6: no session, no FieldsWithUniqueIdentifier
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
            ]);
        }
    }
}

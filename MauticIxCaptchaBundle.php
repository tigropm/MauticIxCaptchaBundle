<?php

declare(strict_types=1);

namespace MauticPlugin\MauticIxCaptchaBundle;

use Mautic\PluginBundle\Bundle\PluginBundleBase;
use MauticPlugin\MauticIxCaptchaBundle\DependencyInjection\Compiler\IntegrationArgumentsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * GDPR-compliant Google reCAPTCHA v3 integration for Mautic forms.
 */
class MauticIxCaptchaBundle extends PluginBundleBase
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Sets the correct constructor arguments for the integration service
        // depending on Mautic version (5, 6, or 7).
        $container->addCompilerPass(new IntegrationArgumentsPass());
    }
}

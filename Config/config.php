<?php

declare(strict_types=1);

use MauticPlugin\MauticIxCaptchaBundle\EventListener\IxCaptchaFormSubscriber;
use MauticPlugin\MauticIxCaptchaBundle\Form\Extension\FieldTypeDefaultLabelExtension;
use MauticPlugin\MauticIxCaptchaBundle\Integration\IxCaptchaIntegration;

return [
    'name'        => 'ixCaptcha',
    'description' => 'GDPR-compliant Google reCAPTCHA v3 integration',
    'version'     => '1.0.0',
    'author'      => 'TGR',

    'services' => [
        'events' => [
            'mautic.ixcaptcha.form.subscriber' => [
                'class'     => IxCaptchaFormSubscriber::class,
                'arguments' => [
                    'event_dispatcher',
                    'translator',
                    'mautic.helper.integration',
                    'mautic.ixcaptcha.service.recaptcha_client',
                ],
            ],
        ],
        'integrations' => [
            'mautic.integration.ixcaptcha' => [
                'class'     => IxCaptchaIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'session',
                    'request_stack',
                    'router',
                    'translator',
                    'logger',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc',
                ],
                'tags' => [
                    'mautic.integration',
                ],
            ],
        ],
        'forms' => [
            'mautic.ixcaptcha.form.extension.default_label' => [
                'class' => FieldTypeDefaultLabelExtension::class,
                'tags'  => ['form.type_extension'],
            ],
        ],
        'others' => [
            'mautic.ixcaptcha.service.recaptcha_client' => [
                'class'     => \MauticPlugin\MauticIxCaptchaBundle\Service\RecaptchaClient::class,
                'arguments' => [
                    'mautic.helper.integration',
                    'monolog.logger.mautic',
                ],
            ],
        ],
    ],
];

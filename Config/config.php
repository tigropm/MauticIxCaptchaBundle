<?php

declare(strict_types=1);

use MauticPlugin\MauticIxCaptchaBundle\EventListener\IxCaptchaFormSubscriber;
use MauticPlugin\MauticIxCaptchaBundle\Form\Extension\FieldTypeDefaultLabelExtension;
use MauticPlugin\MauticIxCaptchaBundle\Integration\IxCaptchaIntegration;
use MauticPlugin\MauticIxCaptchaBundle\Service\RecaptchaClient;

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
                    'translator',
                    'mautic.helper.integration',
                    'mautic.ixcaptcha.service.recaptcha_client',
                ],
            ],
        ],

        'integrations' => [
            'mautic.integration.ixcaptcha' => [
                'class' => IxCaptchaIntegration::class,
                /*
                 * No explicit 'arguments' here — Symfony autowiring resolves
                 * the AbstractIntegration constructor automatically.
                 * This makes the plugin compatible with Mautic 5 (SessionInterface),
                 * Mautic 6, and Mautic 7 (SessionInterface removed) without
                 * any version-specific branching.
                 */
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
                'class'     => RecaptchaClient::class,
                'arguments' => [
                    'mautic.helper.integration',
                    'monolog.logger.mautic',
                    // symfony/http-client — available in Mautic 5, 6, and 7.
                    'http_client',
                    'request_stack',
                ],
            ],
        ],
    ],
];

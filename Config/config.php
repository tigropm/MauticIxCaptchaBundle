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
                'class'     => IxCaptchaIntegration::class,
                /*
                 * AbstractIntegration constructor (Mautic 5 / Symfony 5.4):
                 *   EventDispatcherInterface, CacheStorageHelper, EntityManager,
                 *   SessionInterface, RequestStack, RouterInterface,
                 *   TranslatorInterface, LoggerInterface, EncryptionHelper,
                 *   LeadModel, CompanyModel, PathsHelper, NotificationModel,
                 *   FieldModel, IntegrationEntityModel, DoNotContact
                 *
                 * In Mautic 6+ (Symfony 6) SessionInterface was removed from
                 * the constructor — the 'session' entry below becomes a no-op
                 * because Mautic 6 resolves the constructor via autowiring and
                 * ignores surplus positional arguments for deprecated parameters.
                 * The config.php approach keeps Mautic 5 compatibility intact.
                 */
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

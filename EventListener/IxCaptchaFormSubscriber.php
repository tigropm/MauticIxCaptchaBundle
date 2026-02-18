<?php

declare(strict_types=1);

namespace MauticPlugin\MauticIxCaptchaBundle\EventListener;

use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\ValidationEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticIxCaptchaBundle\Form\Type\IxCaptchaType;
use MauticPlugin\MauticIxCaptchaBundle\Integration\IxCaptchaIntegration;
use MauticPlugin\MauticIxCaptchaBundle\Service\RecaptchaClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Event subscriber for registering the ixCaptcha form field and validation.
 */
class IxCaptchaFormSubscriber implements EventSubscriberInterface
{
    public const FIELD_TYPE = 'plugin.ixcaptcha';
    public const VALIDATION_EVENT = 'mautic.plugin.ixcaptcha.validate';

    private EventDispatcherInterface $dispatcher;
    private TranslatorInterface $translator;
    private IntegrationHelper $integrationHelper;
    private RecaptchaClient $recaptchaClient;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        TranslatorInterface $translator,
        IntegrationHelper $integrationHelper,
        RecaptchaClient $recaptchaClient
    ) {
        $this->dispatcher = $dispatcher;
        $this->translator = $translator;
        $this->integrationHelper = $integrationHelper;
        $this->recaptchaClient = $recaptchaClient;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD    => ['onFormBuild', 0],
            self::VALIDATION_EVENT       => ['onFormValidate', 0],
        ];
    }

    /**
     * Register the custom ixCaptcha field and validator.
     */
    public function onFormBuild(FormBuilderEvent $event): void
    {
        // Get integration settings
        $integration = $this->integrationHelper->getIntegrationObject(IxCaptchaIntegration::INTEGRATION_NAME);

        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            return;
        }

        $keys = $integration->getKeys();
        $siteKey = $keys['site_key'] ?? '';

        // Build translated string bags for every available locale.
        // The template selects the right locale via field.properties.language.
        $translationsDir = __DIR__ . '/../Translations';
        $translations    = [];
        if (is_dir($translationsDir)) {
            foreach (scandir($translationsDir) as $locale) {
                if ($locale === '.' || $locale === '..') {
                    continue;
                }
                if (is_dir($translationsDir . '/' . $locale)) {
                    $translations[$locale] = [
                        'consent_notice'    => $this->translator->trans('mautic.ixcaptcha.default.consent_notice', [], 'messages', $locale),
                        'consent_button'    => $this->translator->trans('mautic.ixcaptcha.default.consent_button', [], 'messages', $locale),
                        'loading'           => $this->translator->trans('mautic.ixcaptcha.loading', [], 'messages', $locale),
                        'submit_blocked'    => $this->translator->trans('mautic.ixcaptcha.submit_blocked', [], 'messages', $locale),
                        'failed'             => $this->translator->trans('mautic.ixcaptcha.validation.failed', [], 'messages', $locale),
                        'privacy_link_prefix'=> $this->translator->trans('mautic.ixcaptcha.default.privacy_link_prefix', [], 'messages', $locale),
                        'privacy_link_word'  => $this->translator->trans('mautic.ixcaptcha.default.privacy_link_word', [], 'messages', $locale),
                        'privacy_link_suffix'=> $this->translator->trans('mautic.ixcaptcha.default.privacy_link_suffix', [], 'messages', $locale),
                    ];
                }
            }
        }

        // Register custom field
        $event->addFormField(self::FIELD_TYPE, [
            'label'        => 'mautic.ixcaptcha.field.label',
            'formType'     => IxCaptchaType::class,
            'template'     => '@MauticIxCaptcha/Integration/ixcaptcha.html.twig',
            'site_key'     => $siteKey,
            'translations' => $translations,
            // Default label pre-filled in the form builder when adding a new field.
            // Handled by FieldTypeDefaultLabelExtension via PRE_SET_DATA.
            'defaultLabel' => $this->translator->trans('mautic.ixcaptcha.field.default_label'),

            'builderOptions' => [
                'addHelpMessage'        => false,
                'addShowLabel'          => true,
                'addDefaultValue'       => false,
                'addLabelAttributes'    => false,
                'addInputAttributes'    => false,
                'addContainerAttributes'=> false,
                'addMappedFieldList'    => false,
                'addSaveResult'         => false,
                'addBehaviorFields'     => false,
                'addIsRequired'         => false,
            ],
        ]);

        // Register validator
        $event->addValidator(self::FIELD_TYPE . '.validator', [
            'eventName' => self::VALIDATION_EVENT,
            'fieldType' => self::FIELD_TYPE,
        ]);
    }

    /**
     * Validate the reCAPTCHA token.
     */
    public function onFormValidate(ValidationEvent $event): void
    {
        $field = $event->getField();
        $value = $event->getValue(); // reCAPTCHA token

        if (empty($value)) {
            $event->failedValidation(
                $this->translator->trans('mautic.ixcaptcha.validation.missing_token')
            );
            return;
        }

        // Call Google reCAPTCHA API
        $result = $this->recaptchaClient->verify($value, $field);

        if (!$result['success']) {
            $event->failedValidation(
                $result['message'] ?? $this->translator->trans('mautic.ixcaptcha.validation.failed')
            );
        }
    }
}

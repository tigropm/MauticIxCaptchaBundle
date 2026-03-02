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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Registers the ixCaptcha form field type and handles server-side token validation.
 */
class IxCaptchaFormSubscriber implements EventSubscriberInterface
{
    public const FIELD_TYPE       = 'plugin.ixcaptcha';
    public const VALIDATION_EVENT = 'mautic.plugin.ixcaptcha.validate';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly IntegrationHelper   $integrationHelper,
        private readonly RecaptchaClient     $recaptchaClient,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD => ['onFormBuild', 0],
            self::VALIDATION_EVENT    => ['onFormValidate', 0],
        ];
    }

    /**
     * Registers the custom field type and its validator with Mautic's form builder.
     */
    public function onFormBuild(FormBuilderEvent $event): void
    {
        /** @var IxCaptchaIntegration|null $integration */
        $integration = $this->integrationHelper->getIntegrationObject(IxCaptchaIntegration::INTEGRATION_NAME);

        if (!$integration instanceof IxCaptchaIntegration
            || !$integration->getIntegrationSettings()->getIsPublished()
        ) {
            return;
        }

        $keys    = $integration->getKeys();
        $siteKey = $keys['site_key'] ?? '';

        // Pre-build translated strings for every available locale so the Twig
        // template can switch languages without hitting the translator at render time.
        $translations = $this->buildTranslations();

        $event->addFormField(self::FIELD_TYPE, [
            'label'        => 'mautic.ixcaptcha.field.label',
            'formType'     => IxCaptchaType::class,
            'template'     => '@MauticIxCaptcha/Integration/ixcaptcha.html.twig',
            'site_key'     => $siteKey,
            'translations' => $translations,
            'btn_color'    => $integration->getButtonColor(),
            // Pre-filled label for new fields (handled by FieldTypeDefaultLabelExtension).
            'defaultLabel' => $this->translator->trans('mautic.ixcaptcha.field.default_label'),

            'builderOptions' => [
                'addHelpMessage'         => false,
                'addShowLabel'           => true,
                'addDefaultValue'        => false,
                'addLabelAttributes'     => false,
                'addInputAttributes'     => false,
                'addContainerAttributes' => false,
                'addMappedFieldList'     => false,
                // NOTE: Do NOT set addSaveResult => false here!
                // Mautic adds any field type with addSaveResult=false to its
                // internal "viewOnlyFields" list. For fields in that list,
                // SubmissionModel::saveSubmission() hits a `continue` statement
                // that skips ALL processing — including our custom validator.
                // Omitting this key keeps the field in the normal processing path
                // so the reCAPTCHA token is validated server-side on every submit.
                'addBehaviorFields'      => false,
                'addIsRequired'          => false,
            ],
        ]);

        $event->addValidator(self::FIELD_TYPE . '.validator', [
            'eventName' => self::VALIDATION_EVENT,
            'fieldType' => self::FIELD_TYPE,
        ]);
    }

    /**
     * Server-side validation of the reCAPTCHA token on form submission.
     */
    public function onFormValidate(ValidationEvent $event): void
    {
        $token = (string) $event->getValue();

        if ($token === '') {
            $event->failedValidation(
                $this->translator->trans('mautic.ixcaptcha.validation.missing_token')
            );

            return;
        }

        $result = $this->recaptchaClient->verify($token);

        if (!$result['success']) {
            $event->failedValidation(
                $this->translator->trans('mautic.ixcaptcha.validation.failed')
            );
        }
    }

    /**
     * Builds the translation string bags for all available locales.
     *
     * @return array<string, array<string, string>>
     */
    private function buildTranslations(): array
    {
        $translationsDir = __DIR__ . '/../Translations';

        if (!is_dir($translationsDir)) {
            return [];
        }

        // Map JS-key → INI-key (read directly from .ini to bypass Symfony translator cache)
        $keys = [
            'consent_notice'      => 'mautic.ixcaptcha.default.consent_notice',
            'consent_button'      => 'mautic.ixcaptcha.default.consent_button',
            'loading'             => 'mautic.ixcaptcha.loading',
            'submit_blocked'      => 'mautic.ixcaptcha.submit_blocked',
            'failed'              => 'mautic.ixcaptcha.validation.failed',
            'privacy_link_prefix' => 'mautic.ixcaptcha.default.privacy_link_prefix',
            'privacy_link_word'   => 'mautic.ixcaptcha.default.privacy_link_word',
            'privacy_link_suffix' => 'mautic.ixcaptcha.default.privacy_link_suffix',
        ];

        $translations = [];

        foreach ((array) scandir($translationsDir) as $locale) {
            if ($locale === '.' || $locale === '..' || !is_dir($translationsDir . '/' . $locale)) {
                continue;
            }

            $iniFile = $translationsDir . '/' . $locale . '/messages.ini';
            if (!file_exists($iniFile)) {
                continue;
            }

            // Read directly from the .ini file — avoids Symfony translator cache entirely,
            // so changes to translation files are reflected immediately without cache:clear.
            $iniValues = parse_ini_file($iniFile) ?: [];

            $bag = [];
            foreach ($keys as $jsKey => $iniKey) {
                $bag[$jsKey] = $iniValues[$iniKey] ?? '';
            }

            $translations[$locale] = $bag;
        }

        return $translations;
    }
}

<?php

declare(strict_types=1);

namespace MauticPlugin\MauticIxCaptchaBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form type for ixCaptcha field configuration in the form builder.
 */
class IxCaptchaType extends AbstractType
{
    /**
     * Dynamically read available locales from the Translations/ directory.
     * Returns e.g. ['Deutsch (de_DE)' => 'de_DE', 'English (en_US)' => 'en_US', ...]
     *
     * @return array<string, string>
     */
    private function getAvailableLocales(): array
    {
        $translationsDir = __DIR__ . '/../../Translations';
        $locales         = [];

        // Human-readable names for supported locales
        $localeNames = [
            'de_DE' => 'Deutsch',
            'en_US' => 'English',
            'fr_FR' => 'Français',
        ];

        if (!is_dir($translationsDir)) {
            return ['English (en_US)' => 'en_US'];
        }

        foreach (scandir($translationsDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $iniFile = $translationsDir . '/' . $entry . '/messages.ini';
            if (is_dir($translationsDir . '/' . $entry) && file_exists($iniFile)) {
                $name           = isset($localeNames[$entry]) ? $localeNames[$entry] . ' (' . $entry . ')' : $entry;
                $locales[$name] = $entry;
            }
        }

        asort($locales);

        return $locales;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // --- Explicit Consent toggle ---
        $builder->add('explicitConsent', \Mautic\CoreBundle\Form\Type\YesNoButtonGroupType::class, [
            'label'      => 'mautic.ixcaptcha.form.explicit_consent',
            'data'       => $options['data']['explicitConsent'] ?? true,
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'tooltip' => 'mautic.ixcaptcha.form.explicit_consent.tooltip',
            ],
        ]);

        // --- Privacy / More info URL (required) ---
        // Note: FieldType wraps all custom properties sub-forms with 'required' => false,
        // so we must add an explicit NotBlank constraint for server-side validation.
        $builder->add('privacyUrl', UrlType::class, [
            'label'       => 'mautic.ixcaptcha.form.privacy_url',
            'label_attr'  => ['class' => 'control-label required'],
            'data'        => $options['data']['privacyUrl'] ?? '',
            'attr'        => [
                'class'       => 'form-control',
                'placeholder' => 'https://example.com/datenschutz',
                'tooltip'     => 'mautic.ixcaptcha.form.privacy_url.tooltip',
            ],
            'required'    => true,
            'constraints' => [
                new Assert\NotBlank(message: 'mautic.ixcaptcha.validation.privacy_url_required'),
                new Assert\Url(message: 'mautic.ixcaptcha.validation.privacy_url_invalid'),
            ],
        ]);

        // --- Language selector ---
        $builder->add('language', ChoiceType::class, [
            'label'       => 'mautic.ixcaptcha.form.language',
            'label_attr'  => ['class' => 'control-label'],
            'choices'     => $this->getAvailableLocales(),
            'data'        => $options['data']['language'] ?? 'de_DE',
            'attr'        => [
                'class'   => 'form-control',
                'tooltip' => 'mautic.ixcaptcha.form.language.tooltip',
            ],
            'placeholder' => false,
            'required'    => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'allow_extra_fields' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'ixcaptcha';
    }
}

<?php

declare(strict_types=1);

namespace MauticPlugin\MauticIxCaptchaBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * reCAPTCHA v3 Integration configuration.
 *
 * Stores site_key and secret_key via getRequiredKeyFields() (encrypted by Mautic).
 * Additional settings (min_score) are stored in the integration's feature settings.
 */
class IxCaptchaIntegration extends AbstractIntegration
{
    public const INTEGRATION_NAME = 'IxCaptcha';

    /** Default minimum reCAPTCHA v3 score (0.0 – 1.0) */
    public const DEFAULT_MIN_SCORE = 0.5;

    /** Default consent button background colour */
    public const DEFAULT_BTN_COLOR = '#f49e00';

    public function getName(): string
    {
        return self::INTEGRATION_NAME;
    }

    public function getDisplayName(): string
    {
        return 'ixCaptcha (reCAPTCHA v3)';
    }

    public function getAuthenticationType(): string
    {
        return 'none';
    }

    public function getRequiredKeyFields(): array
    {
        return [
            'site_key'   => 'mautic.ixcaptcha.config.site_key',
            'secret_key' => 'mautic.ixcaptcha.config.secret_key',
        ];
    }

    /**
     * Returns the configured minimum score, falling back to the default.
     * Safe to call from RecaptchaClient without exposing raw settings access.
     */
    public function getMinScore(): float
    {
        $settings = $this->getIntegrationSettings()->getFeatureSettings();

        return isset($settings['min_score'])
            ? (float) $settings['min_score']
            : self::DEFAULT_MIN_SCORE;
    }

    /**
     * Returns the configured button colour, falling back to the default.
     */
    public function getButtonColor(): string
    {
        $settings = $this->getIntegrationSettings()->getFeatureSettings();
        $color    = trim((string) ($settings['btn_color'] ?? self::DEFAULT_BTN_COLOR));

        // Mautic's data-toggle="color" picker stores the value without the leading #.
        // Normalise to #rrggbb regardless of whether # is present or not.
        if (preg_match('/^#?([0-9a-fA-F]{6})$/', $color, $m)) {
            return '#' . $m[1];
        }

        return self::DEFAULT_BTN_COLOR;
    }

    /**
     * Adds settings fields to the integration's Features tab in the Mautic admin UI.
     *
     * {@inheritdoc}
     */
    public function appendToForm(&$builder, $data, $formArea): void
    {
        if ($formArea !== 'features') {
            return;
        }

        $builder->add('min_score', NumberType::class, [
            'label'      => 'mautic.ixcaptcha.config.min_score',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class'   => 'form-control',
                'min'     => '0.0',
                'max'     => '1.0',
                'step'    => '0.05',
                'tooltip' => 'mautic.ixcaptcha.config.min_score.tooltip',
            ],
            'data'       => $data['min_score'] ?? self::DEFAULT_MIN_SCORE,
            'required'   => false,
            'scale'      => 2,
        ]);

        $btnColor = (isset($data['btn_color']) && $data['btn_color'] !== '')
            ? $data['btn_color']
            : self::DEFAULT_BTN_COLOR;

        $builder->add('btn_color', TextType::class, [
            'label'      => 'mautic.ixcaptcha.config.btn_color',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class'       => 'form-control',
                'data-toggle' => 'color',
                'tooltip'     => 'mautic.ixcaptcha.config.btn_color.tooltip',
                'placeholder' => self::DEFAULT_BTN_COLOR,
            ],
            'data'       => $btnColor,
            'required'   => false,
        ]);
    }
}

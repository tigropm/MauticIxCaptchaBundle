<?php

declare(strict_types=1);

namespace MauticPlugin\MauticIxCaptchaBundle\Form\Extension;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Mautic\FormBundle\Form\Type\FieldType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Pre-fills the "label" input in the Mautic form field builder modal with a
 * default value when a custom field type declares 'defaultLabel' in its
 * customParameters — but only for new fields that have no label yet.
 *
 * How it works:
 *   FieldController::getFieldForm() passes ['customParameters' => $customParams]
 *   as form options to FieldType. These options are therefore available via
 *   $options['customParameters'] inside this extension's PRE_SET_DATA listener.
 */
class FieldTypeDefaultLabelExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [FieldType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Ensure the customParameters option is declared so Symfony does not
        // complain when FieldType already declares it.
        $resolver->setDefined(['customParameters']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Read defaultLabel from the custom field's registration parameters.
        $defaultLabel = $options['customParameters']['defaultLabel'] ?? null;

        if ($defaultLabel === null) {
            // This field type has no defaultLabel — nothing to do.
            return;
        }

        // Determine right here in buildForm() whether this is a new field.
        // $options['data'] is the raw data array passed to createForm() before
        // PRE_SET_DATA fires, so it reflects the original entity state.
        $data  = $options['data'] ?? [];
        $isNew = empty($data['id']) || str_starts_with((string) ($data['id'] ?? ''), 'new');

        if ($isNew) {
            // Override the showLabel toggle that FieldType already added with
            // data:true by re-adding it with data:false. Calling $builder->add()
            // with the same field name replaces the previous definition.
            if (!isset($data['showLabel'])) {
                $builder->add(
                    'showLabel',
                    YesNoButtonGroupType::class,
                    [
                        'label' => 'mautic.form.field.form.showlabel',
                        'data'  => false,
                    ]
                );
            }
        }

        // PRE_SET_DATA: pre-fill the label text for new fields.
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            static function (FormEvent $event) use ($defaultLabel): void {
                $data = $event->getData();

                $isNew = empty($data['id']) || str_starts_with((string) ($data['id'] ?? ''), 'new');

                if (!$isNew) {
                    return;
                }

                if (empty($data['label'])) {
                    $data['label'] = $defaultLabel;
                    $event->setData($data);
                }
            }
        );
    }
}

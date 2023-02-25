<?php

namespace Coa\VideolibraryBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class ScreenshotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('imgs', FileType::class, [
                'label' => "<i class='fa fa-plus fa-2x'></i>",
                'label_html' => true,
                'label_attr' => [
                    'class' => 'screenshot-label'
                ],
                'attr' => [
                    'class' => 'screenshot-add'
                ],
                'constraints' => [
                    new Image([
                        'maxSize' => '1M',
                        'mimeTypes' => [
                            "image/jpeg",
                            "image/jpg",
                            "image/png"
                        ],
                        'minRatio' => '1.78',
                        'minRatioMessage' => "Le ratio autorisé est 16:9 pour les images",
                        'maxRatio' => '1.78',
                        'maxRatioMessage' => "Le ratio autorisé est 16:9 pour les images",
                        'allowLandscape' => true
                    ])
                ]
            ])
        ;
    }

    public function getBlockPrefix()
    {
        return '';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'required' => false
        ]);
    }
}
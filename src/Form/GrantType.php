<?php

namespace Coa\VideolibraryBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GrantType extends AbstractType
{
    private bool $isEnabled = true;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $grant_type = $builder->getData();

        if($grant_type->getId()) {
            $this->isEnabled = $grant_type->getIsEnabled();
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Entrer le nom du scope'
                ]
            ])
            ->add('label', TextType::class, [
                'label' => 'Label',
                'attr' => [
                    'placeholder' => 'Entrer le label du scope'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Entrer la description du scope'
                ],
                'required' => false
            ])
            ->add('isEnabled', CheckboxType::class, [
                    'required' => false,
                    'label' => false,
                    'attr' => [
                        'checked' => $this->isEnabled ? true : false,
                        'data-toggle' => 'toggle',
                        'data-on' => 'Activé',
                        'data-off' => 'Désactivé',
                        'data-onstyle' => 'gradient-success',
                        'data-offstyle' => 'gradient-danger'
                    ]
                ]
            )
        ;

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => \Coa\VideolibraryBundle\Entity\GrantType::class
        ]);
    }
}
<?php

namespace Coa\VideolibraryBundle\Form;

use Coa\VideolibraryBundle\Entity\Client;
use Coa\VideolibraryBundle\Entity\Scope;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
{
    private $scopes = [];
    private $grantTypes = [];
    private bool $isEnabled = true;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $client = $builder->getData();

        if ($client->getId()) {
            $this->scopes = $client->getScopes();
            $this->grantTypes = $client->getGrantTypes();
            $this->isEnabled = $client->getIsEnabled();
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Entrer le nom du client'
                ]
            ])
            ->add('grantTypes', EntityType::class, [
                'required' => false,
                'label' => 'Grant Types',
                'class' => \Coa\VideolibraryBundle\Entity\GrantType::class,
                'query_builder' => function(EntityRepository $er) {
                    return $er->createQueryBuilder('qb')
                                ->andWhere('qb.isEnabled = true');
                },
                'choice_label' => 'label',
                'multiple' => true,
                'attr' => [
                    'class' => 'multiselect-dropdown mb-3'
                ],
                'choice_attr' => function($choice, $key, $val) {
                    foreach ($this->grantTypes as $grantType) {
                        if($grantType->getId() == $val) {
                            return ['selected' => 'selected'];
                        }
                    }
                    return [];
                }
            ])
            ->add('domain', UrlType::class, [
                'label' => 'Domaine',
                'attr' => [
                    'placeholder' => 'Entrer l\'url du website'
                ],
                'required' => false
            ])
            ->add('postbackUrl', UrlType::class, [
                'label' => 'Postback Url',
                'attr' => [
                    'placeholder' => 'Entrer l\'url de postback'
                ],
                'required' => false
            ])
            ->add('hlsKeyBaseurl', UrlType::class, [
                'label' => 'HLS Key Baseurl',
                'attr' => [
                    'placeholder' => 'Entrer l\'url des clés HLS'
                ],
                'required' => false
            ])
            ->add('scopes', EntityType::class, [
                'required' => false,
                'label' => 'Scopes',
                'class' => Scope::class,
                'query_builder' => function(EntityRepository $er) {
                    return $er->createQueryBuilder('qb')
                        ->andWhere('qb.isEnabled = true');
                },
                'choice_label' => 'name',
                'multiple' => true,
                'attr' => [
                    'class' => 'multiselect-dropdown mb-3'
                ],
                'choice_attr' => function($choice, $key, $val) {
                    foreach ($this->scopes as $scope) {
                        if($scope->getId() == $val) {
                            return ['selected' => 'selected'];
                        }
                    }
                    return [];
                }
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
            ->add('routingSuffix', TextType::class, [
                'label' => 'Routing suffix',
                'attr' => [
                    'placeholder' => 'Entrer le routing suffix'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Client::class
        ]);
    }
}
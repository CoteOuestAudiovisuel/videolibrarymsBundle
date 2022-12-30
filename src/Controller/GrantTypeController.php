<?php

namespace Coa\VideolibraryBundle\Controller;

use Coa\VideolibraryBundle\Entity\GrantType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/grant_type", name="coa_videolibrary_grant_type_")
 */
class GrantTypeController extends AbstractController
{
    private ManagerRegistry $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @Route("/", name="index")
     * @return Response
     */
    public function index(): Response
    {
        $em = $this->managerRegistry->getManager();
        $grantTypes = $em->getRepository(GrantType::class)->findBy([], ['id' => 'DESC']);

        return $this->render('@CoaVideolibrary/grant_type/index.html.twig', [
            'grant_types' => $grantTypes
        ]);
    }

    /**
     * @Route("/add", name="add")
     * @param Request $request
     * @return Response
     */
    public function add(Request $request): Response
    {
        $grantType = new GrantType();
        $form = $this->createForm(\Coa\VideolibraryBundle\Form\GrantType::class, $grantType);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->managerRegistry->getManager();
            $em->persist($grantType);
            $em->flush();

            $this->addFlash('success', 'Ajouté avec succès');

            return $this->redirectToRoute('coa_videolibrary_grant_type_index');
        }

        return $this->render('@CoaVideolibrary/grant_type/form.html.twig', [
            'form' => $form->createView(),
            'type' => 'add'
        ]);
    }

    /**
     * @Route("/edit/{id}", name="edit")
     * @param GrantType $grantType
     * @param Request $request
     * @return Response
     */
    public function edit(GrantType $grantType, Request $request): Response
    {
        $form = $this->createForm(\Coa\VideolibraryBundle\Form\GrantType::class, $grantType);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->managerRegistry->getManager();
            $em->persist($grantType);
            $em->flush();

            $this->addFlash('success', 'Modifié avec succès');

            return $this->redirectToRoute('coa_videolibrary_grant_type_index');
        }

        return $this->render('@CoaVideolibrary/grant_type/form.html.twig', [
            'form' => $form->createView(),
            'type' => 'edit'
        ]);
    }

    /**
     * @Route("/delete/{id}", name="delete")
     * @param GrantType $grantType
     * @param Request $request
     * @return Response
     */
    public function delete(GrantType $grantType, Request $request): Response
    {
        if($this->isCsrfTokenValid('grant_type' . $grantType->getId(), $request->request->get('_csrf_token'))) {
            $em = $this->managerRegistry->getManager();
            $em->remove($grantType);
            $em->flush();

            $this->addFlash('success', "Supprimé avec succès");

        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide');
        }

        return $this->redirectToRoute('coa_videolibrary_grant_type_index');
    }
}
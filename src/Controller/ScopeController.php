<?php

namespace Coa\VideolibraryBundle\Controller;

use Coa\VideolibraryBundle\Entity\Scope;
use Coa\VideolibraryBundle\Form\ScopeType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/scope", name="coa_videolibrary_scope_")
 */
class ScopeController extends AbstractController
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
        $scopes = $em->getRepository(Scope::class)->findBy([], ['id' => 'DESC']);

        return $this->render('@CoaVideolibrary/scope/index.html.twig', [
            'scopes' => $scopes
        ]);
    }

    /**
     * @Route("/add", name="add")
     * @param Request $request
     * @return Response
     */
    public function add(Request $request): Response
    {
        $scope = new Scope();
        $form = $this->createForm(ScopeType::class, $scope);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->managerRegistry->getManager();
            $em->persist($scope);
            $em->flush();

            $this->addFlash('success', 'Ajouté avec succès');

            return $this->redirectToRoute('coa_videolibrary_scope_index');
        }

        return $this->render('@CoaVideolibrary/scope/form.html.twig', [
            'form' => $form->createView(),
            'type' => 'add'
        ]);
    }

    /**
     * @Route("/edit/{id}", name="edit")
     * @param Scope $scope
     * @param Request $request
     * @return Response
     */
    public function edit(Scope $scope, Request $request): Response
    {
        $form = $this->createForm(ScopeType::class, $scope);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->managerRegistry->getManager();
            $em->persist($scope);
            $em->flush();

            $this->addFlash('success', 'Modifié avec succès');

            return $this->redirectToRoute('coa_videolibrary_scope_index');
        }

        return $this->render('@CoaVideolibrary/scope/form.html.twig', [
            'form' => $form->createView(),
            'type' => 'edit'
        ]);
    }

    /**
     * @Route("/delete/{id}", name="delete")
     * @param Scope $scope
     * @param Request $request
     * @return Response
     */
    public function delete(Scope $scope, Request $request): Response
    {
        if($this->isCsrfTokenValid('scope' . $scope->getId(), $request->request->get('_csrf_token'))) {
            $em = $this->managerRegistry->getManager();
            $em->remove($scope);
            $em->flush();

            $this->addFlash('success', "Supprimé avec succès");

        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide');
        }

        return $this->redirectToRoute('coa_videolibrary_scope_index');
    }
}
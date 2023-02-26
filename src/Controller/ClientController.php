<?php

namespace Coa\VideolibraryBundle\Controller;

use Coa\VideolibraryBundle\Entity\Client;
use Coa\VideolibraryBundle\Entity\ClientScope;
use Coa\VideolibraryBundle\Form\ClientType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/client", name="coa_videolibrary_client_")
 */
class ClientController extends AbstractController
{
    private ManagerRegistry $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @Route("/", name="index")
     * @return void
     */
    public function index (): Response
    {
        $em = $this->managerRegistry->getManager();
        $clients = $em->getRepository(Client::class)->findBy([], ['id' => 'DESC']);

        return $this->render('@CoaVideolibrary/client/index.html.twig', [
            'clients' => $clients
        ]);
    }

    /**
     * @Route("/add", name="add")
     * @param Request $request
     * @return Response
     */
    public function add(Request $request): Response
    {
        $ftpPath = $this->container->get('kernel.project_dir') . "/coa_videolibrary_ftp";
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->managerRegistry->getManager();
            $clientId = substr(trim(base64_encode(bin2hex(openssl_random_pseudo_bytes(64,$ok))),"="),0,16);
            $clientSecret = substr(trim(base64_encode(bin2hex(openssl_random_pseudo_bytes(64,$ok))),"="),0,32);
            $clientToken = base64_encode(sprintf("%s:%s", $clientId, $clientSecret));

            $client
                ->setClientId($clientId)
                ->setClientSecret($clientSecret)
                ->setClientToken($clientToken)
            ;

            $em->persist($client);
            $em->flush();

            // creation du dossier ftp de ce client
            $cname = strtolower($client->getName());
            $clientftpDir = $ftpPath . "/".$cname;
            if(!file_exists($clientftpDir)){
                mkdir($clientftpDir);
            }

            $this->addFlash("success", "Ajouté avec succès");

            return $this->redirectToRoute('coa_videolibrary_client_index');
        }

        return $this->render('@CoaVideolibrary/client/form.html.twig', [
            'form' => $form->createView(),
            'type' => 'add'
        ]);
    }

    /**
     * @Route("/edit/{id}", name="edit")
     * @param Client $client
     * @param Request $request
     * @return Response
     */
    public function edit(Client $client, Request $request): Response
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->managerRegistry->getManager();

            $em->persist($client);
            $em->flush();

            $this->addFlash('success', 'Modifié avec succès');

            return $this->redirectToRoute('coa_videolibrary_client_index');
        }

        return $this->render("@CoaVideolibrary/client/form.html.twig", [
            "form" => $form->createView(),
            "client" => $client,
            "type" => "edit"
        ]);
    }

    /**
     * @Route("/delete/{id}", name="delete")
     * @param Client $client
     * @param Request $request
     * @return Response
     */
    public function delete(Client $client, Request $request): Response
    {
        if($this->isCsrfTokenValid('client' . $client->getId(), $request->request->get('_csrf_token'))) {
            $em = $this->managerRegistry->getManager();
            $em->remove($client);
            $em->flush();

            $this->addFlash('success', 'Supprimé avec succès');
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide');
        }

        return $this->redirectToRoute('coa_videolibrary_client_index');
    }
}
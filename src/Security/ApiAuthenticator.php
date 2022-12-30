<?php

namespace Coa\VideolibraryBundle\Security;

use Coa\VideolibraryBundle\Entity\AccessToken;
use Coa\VideolibraryBundle\Service\APIException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Translation\TranslatorInterface;

class ApiAuthenticator extends AbstractAuthenticator
{
    private ManagerRegistry $managerRegistry;
    private TranslatorInterface $translator;
    private RequestStack $requestStack;

    public function __construct(ManagerRegistry $managerRegistry, TranslatorInterface $translator, RequestStack $requestStack)
    {
        $this->managerRegistry = $managerRegistry;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authorization = $request->headers->get('Authorization');
        if (null === $authorization) {
            // The token header was empty, authentication fails with HTTP Status
            // Code 401 "Unauthorized"
            throw new CustomUserMessageAuthenticationException('L\'utilisateur n\'est pas connecté');
        }

        $exploded_authorization = explode(" ", $authorization);

        if(count($exploded_authorization) !== 2) {
            throw new APIException(
                400,
                'invalid_authorization',
                "Le code d'authorisation est mal formé",
                400
            );
        }

        if(strtolower($exploded_authorization[0]) !== "bearer") {
            throw new APIException(
                400,
                'invalid_authorization',
                "Ce type d'authorisation pour l'authentification n'est pas pris en charge",
                400
            );
        }

        $token = end($exploded_authorization);

        $accessToken = $this->managerRegistry
            ->getRepository(AccessToken::class)
            ->findOneBy(['accessToken' => $token]);

        if(!$accessToken) {
            throw new APIException(
                401,
                'invalid_token',
                "Le client ne peut pas être connecté avec ce token",
                401
            );
        }

        $cliend_id = $accessToken->getClient()->getClientId();

        $passport = new SelfValidatingPassport(new UserBadge($cliend_id, function ($credentials) use (&$token) {
            return $this->managerRegistry
                ->getRepository(AccessToken::class)
                ->findOneBy(['accessToken' => $token])->getClient();
        },), []);

        $passport->getUser()->setRoles(json_decode($accessToken->getScope()));

        $passport->setAttribute('access_token', [
            "token" => $accessToken->getAccessToken(),
            "refresh_token" => $accessToken->getRefreshToken(),
            "expires_in" => $accessToken->getExpiresIn()->getTimestamp(),
            "scopes" => json_decode($accessToken->getScope()),
            "revoked" => $accessToken->getRevoked()
        ]);

        /*$this->requestStack->getCurrentRequest()->attributes->set('access_token', [
            "token" => $accessToken->getAccessToken(),
            "refresh_token" => $accessToken->getRefreshToken(),
            "expires_in" => $accessToken->getExpiresIn()->getTimestamp(),
            "revoked" => $accessToken->getRevoked()
        ]);*/

        return $passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);

        $token->setAttributes($passport->getAttributes());

        return $token;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        throw new APIException(
            401,
            'invalid_request',
            $exception->getMessage(),
            401
        );
    }

//    public function start(Request $request, AuthenticationException $authException = null): Response
//    {
//        /*
//         * If you would like this class to control what happens when an anonymous user accesses a
//         * protected page (e.g. redirect to /login), uncomment this method and make this class
//         * implement Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface.
//         *
//         * For more details, see https://symfony.com/doc/current/security/experimental_authenticators.html#configuring-the-authentication-entry-point
//         */
//    }
}

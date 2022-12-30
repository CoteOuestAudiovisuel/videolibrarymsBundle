<?php

namespace Coa\VideolibraryBundle\Security\Voter;

use Coa\VideolibraryBundle\Entity\Client;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class ClientVoter extends Voter
{
    const UPLOAD = 'upload';

    protected function supports(string $attribute, $subject = null): bool
    {

        if (!in_array($attribute, [ self::UPLOAD ])) {
            return false;
        }

        /*if (!$subject instanceof Client) {
            return false;
        }*/

        return true;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $client = $token->getUser();

        if (!$client instanceof Client) {
            // the user must be logged in; if not, deny access
            return false;
        }

        $scope = $token->getAttribute('access_token')['scopes'];

        switch ($attribute) {
            case self::UPLOAD:
                return $this->canUpload($client, $scope);
        }

        return false;
    }

    private function canUpload(Client $client, array $scopes): bool
    {
        $client_scopes = array_map(function ($item) {
            return $item->getLabel();
        }, $client->getScopes()->toArray());

        foreach ($scopes as $req_scope) {
            if (!in_array($req_scope, $client_scopes)) {
                return false;
            }
        }

        return true;
    }
}
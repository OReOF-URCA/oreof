<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Security/LoginCasAuthenticator.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Security;

use App\Events\CASAuthenticationFailureEvent;
use phpCAS;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class LoginCasAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly ParameterBagInterface $parameterBag, private readonly RouterInterface $router, private readonly UrlGeneratorInterface $urlGenerator)
    {
    }
    public function supports(Request $request): ?bool
    {
        return '/sso/cas' === $request->getPathInfo();
    }

    public function authenticate(Request $request): Passport
    {
        $username = $this->getCredentials();
        if (null === $username) {
            // The token header was empty, authentication fails with HTTP Status
            // Code 401 "Unauthorized"
            throw new CustomUserMessageAuthenticationException('Authentification CAS incorrecte. Utilisateur inconnu.');
        }

        return new SelfValidatingPassport(new UserBadge($username));
    }

    public function getCredentials(): ?string
    {
        $cas_host = $this->parameterBag->get('CAS_HOST');
        $cas_context = $this->parameterBag->get('CAS_CONTEXT');
        $cas_port = (int)$this->parameterBag->get('CAS_PORT');
        $client_service_name = $this->parameterBag->get('CAS_CLIENT_SERVICE_NAME');
        phpCAS::setVerbose(true);
        phpCAS::client(CAS_VERSION_3_0, $cas_host, $cas_port, $cas_context, $client_service_name);
        phpCAS::setFixedServiceURL($this->urlGenerator->generate(
            'cas_return',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        ));
        phpCAS::setNoCasServerValidation();
        phpCAS::forceAuthentication();

        if (phpCAS::getUser()) {
            return phpCAS::getUser();
        }

        return null;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof \App\Entity\User && $user->isIsEnable() === false) {
            throw new CustomUserMessageAuthenticationException('Votre compte est désactivé. Veuillez contacter l\'administrateur.');
        }

        return new RedirectResponse(
            $this->router->generate('app_homepage')
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $def_response = new RedirectResponse(
            $this->router->generate('app_login')
        );

        return (new CASAuthenticationFailureEvent($exception, $def_response))->getResponse();
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

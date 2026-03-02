<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private UrlGeneratorInterface $urlGenerator;
    private HttpClientInterface $httpClient;
    private UserRepository $userRepository;
    private string $recaptchaSecret;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        HttpClientInterface $httpClient,
        UserRepository $userRepository,
        string $recaptchaSecret
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->httpClient = $httpClient;
        $this->userRepository = $userRepository;
        $this->recaptchaSecret = $recaptchaSecret;
    }

    public function authenticate(Request $request): Passport
    {
        // ==========================
        // 🔐 1️⃣ Vérification reCAPTCHA
        // ==========================

        $recaptchaResponse = $request->request->get('g-recaptcha-response');

        if (!$recaptchaResponse) {
            throw new CustomUserMessageAuthenticationException('Veuillez valider le captcha.');
        }

        $response = $this->httpClient->request(
            'POST',
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'body' => [
                    'secret' => $this->recaptchaSecret,
                    'response' => $recaptchaResponse,
                ],
            ]
        );

        $data = $response->toArray();

        if (!$data['success']) {
            throw new CustomUserMessageAuthenticationException('Captcha invalide.');
        }

        // ==========================
        // 🔐 2️⃣ Vérification utilisateur
        // ==========================

        $email = $request->request->get('email', '');

        $request->getSession()->set(
            SecurityRequestAttributes::LAST_USERNAME,
            $email
        );

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
        }

        // 🔥 Vérification BAN
        if ($user->isBanned()) {
            throw new CustomUserMessageAuthenticationException(
                'Votre compte a été suspendu. Contactez l’administrateur.'
            );
        }

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->request->get('password')),
            [
                new CsrfTokenBadge(
                    'authenticate',
                    $request->request->get('_csrf_token')
                ),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        $user = $token->getUser();
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse(
                $this->urlGenerator->generate('admin_dashboard')
            );
        }

        if (in_array('ROLE_MEDECIN', $roles, true)) {
            return new RedirectResponse(
                $this->urlGenerator->generate('medecin_rendez_vous_index')
            );
        }

        if (in_array('ROLE_PATIENT', $roles, true)) {
            return new RedirectResponse(
                $this->urlGenerator->generate('app_frontend_vue')
            );
        }

        return new RedirectResponse(
            $this->urlGenerator->generate('app_frontend_vue')
        );
    }

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): Response {

        $request->getSession()->set(
            SecurityRequestAttributes::AUTHENTICATION_ERROR,
            $exception
        );

        return new RedirectResponse(
            $this->urlGenerator->generate(self::LOGIN_ROUTE)
        );
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}

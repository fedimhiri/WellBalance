<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class GoogleAuthenticator extends AbstractAuthenticator
{
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $googleUser = $client->fetchUser();

        $email = $googleUser->getEmail();
        $firstname = $googleUser->getFirstName() ?? 'user';
        $lastname = $googleUser->getLastName() ?? rand(100, 999);
        $avatar = $googleUser->getAvatar();

        return new SelfValidatingPassport(
            new UserBadge($email, function () use ($email, $firstname, $lastname, $avatar) {

                $repository = $this->entityManager->getRepository(User::class);
                $user = $repository->findOneBy(['email' => $email]);

                if (!$user) {

                    $user = new User();
                    $user->setEmail($email);

                    // Username unique
                    $baseUsername = strtolower($firstname . '.' . $lastname);
                    $username = $baseUsername;
                    $counter = 1;

                    while ($repository->findOneBy(['username' => $username])) {
                        $username = $baseUsername . $counter;
                        $counter++;
                    }

                    $user->setUsername($username);
                    $user->setTelephone('00000000');
                    $user->setRoles(['ROLE_USER']);
                    $user->setPassword(bin2hex(random_bytes(10)));

                    if ($avatar) {
                        $user->setAvatar($avatar);
                    }

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?RedirectResponse {

        /** @var User $user */
        $user = $token->getUser();
        $roles = $user->getRoles();

        // 🔴 1️⃣ Vérification BAN
        if ($user->isBanned()) {

            $request->getSession()->invalidate();

            $request->getSession()->getFlashBag()->add(
                'danger',
                'Votre compte a été suspendu. Contactez l’administration.'
            );

            return new RedirectResponse(
                $this->urlGenerator->generate('app_login')
            );
        }

        // 🔵 2️⃣ Téléphone obligatoire
        if ($user->getTelephone() === '00000000') {
            return new RedirectResponse(
                $this->urlGenerator->generate('frontend_profile')
            );
        }

        // 🟣 3️⃣ Admin
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse(
                $this->urlGenerator->generate('admin_dashboard')
            );
        }

        // 🟢 4️⃣ User normal
        return new RedirectResponse(
            $this->urlGenerator->generate('app_frontend_vue')
        );
    }

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): ?RedirectResponse {

        return new RedirectResponse(
            $this->urlGenerator->generate('app_login')
        );
    }
}

<?php

use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;

require_once __DIR__ . '/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

    return new class($kernel) {
        public function __construct(private Kernel $kernel) {}

        public function __invoke()
        {
            try {
                $this->kernel->boot();
                $container = $this->kernel->getContainer();
                /** @var EntityManagerInterface $em */
                $em = $container->get('doctrine')->getManager();
                // Bypass service container issue using native PHP hash (Symfony default is usually bcrypt/sodium compatible)
                // $hasher = $container->get(UserPasswordHasherInterface::class);

                echo "Creating Admin User...\n";
                $userRepository = $em->getRepository(User::class);
                
                // Check if admin exists
                $admin = $userRepository->findOneBy(['email' => 'admin@wellbalance.com']);

                if (!$admin) {
                    $admin = new User();
                    $admin->setEmail('admin@wellbalance.com');
                    $admin->setUsername('Admin User');
                    $admin->setRoles(['ROLE_ADMIN']);
                    $admin->setTelephone('99887766'); // Dummy phone
                    
                    // Hash password using native PHP (compatible with auto-configured upgradable password hashers)
                    // Default for new Symfony apps is usually bcrypt or sodium
                    $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
                    $admin->setPassword($hashedPassword);

                    $em->persist($admin);
                    $em->flush();
                    echo "✅ Admin created successfully!\n";
                } else {
                    echo "ℹ️ Admin already exists. Updating roles...\n";
                    $admin->setRoles(['ROLE_ADMIN']);
                    
                     // Reset password just in case
                    $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
                    $admin->setPassword($hashedPassword);
                    
                    $em->flush();
                    echo "✅ Admin updated successfully!\n";
                }

                echo "--------------------------------------------------\n";
                echo "Email:    admin@wellbalance.com\n";
                echo "Password: admin123\n";
                echo "--------------------------------------------------\n";

            } catch (\Throwable $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
                echo "File: " . $e->getFile() . "\n";
                echo "Line: " . $e->getLine() . "\n";
            }
        }
    };
};

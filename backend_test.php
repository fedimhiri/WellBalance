<?php

use App\Entity\User;
use App\Entity\PlanNutrition;
use App\Entity\Repas;
use App\Entity\ObjectifSportif;
use App\Entity\ActivitePhysique;
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

                echo "1. Testing User & Nutrition Integration...\n";
                $userRepository = $em->getRepository(User::class);
                // Try to find the specific test user we create
                $user = $userRepository->findOneBy(['username' => 'backend_test']);

                if (!$user) {
                    echo "   Test user not found, creating one...\n";
                    $user = new User();
                    $user->setEmail('backend_test@test.com');
                    $user->setUsername('backend_test');
                    $user->setPassword('$2y$13$abcdef...'); 
                    $user->setRoles(['ROLE_USER']);
                    $user->setTelephone('12345678'); 
                    $em->persist($user);
                    $em->flush();
                }
                
                echo "   User found/created: " . $user->getEmail() . "\n";

                // Create Plan Nutrition
                $plan = new PlanNutrition();
                $plan->setDateDebut(new \DateTime());
                $plan->setDateFin(new \DateTime('+7 days'));
                $plan->setObjectif('Perte de poids');
                $plan->setUser($user);
                
                $em->persist($plan);
                
                // Create Repas
                $repas = new Repas();
                $repas->setTypeRepas('Déjeuner');
                $repas->setCalories(500);
                $repas->setDateRepas(new \DateTime());
                $repas->setPlanNutrition($plan);
                
                $em->persist($repas);
                $em->flush();
                
                echo "   [OK] PlanNutrition and Repas created and linked to User.\n";

                echo "2. Testing Sports Integration...\n";
                
                // Create ObjectifSportif
                $objectif = new ObjectifSportif();
                $objectif->setLibelle('Courir 10km');
                $objectif->setTypeObjectif('Endurance');
                $objectif->setDateDebut(new \DateTime());
                $objectif->setStatut('En cours');
                
                $em->persist($objectif);
                
                // Create ActivitePhysique
                $activite = new ActivitePhysique();
                $activite->setNom('Jogging matinal');
                $activite->setTypeActivite('Course');
                $activite->setDureeEstimee(45);
                $activite->setCaloriesEstimees(400);
                $activite->setActif(true);
                $activite->setObjectifSportif($objectif);
                $activite->setNiveau('Débutant'); 
                
                $em->persist($activite);
                $em->flush();

                echo "   [OK] ObjectifSportif and ActivitePhysique created.\n";

                echo "Backend verification complete!\n";
            } catch (\Throwable $e) {
                file_put_contents('error.txt', "ERROR: " . $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\nTrace:\n" . $e->getTraceAsString());
            }
        }
    };
};

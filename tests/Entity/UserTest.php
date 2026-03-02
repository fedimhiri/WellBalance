<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $user = new User();

        $user->setEmail('test@wellbalance.com');
        $user->setUsername('johndoe');
        $user->setTelephone('12345678');

        $this->assertSame('test@wellbalance.com', $user->getEmail());
        $this->assertSame('johndoe', $user->getUsername());
        $this->assertSame('12345678', $user->getTelephone());
    }

    public function testDefaultRoleIsUser(): void
    {
        $user = new User();

        $roles = $user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
    }

    public function testUserIdentifier(): void
    {
        $user = new User();
        $user->setEmail('test@wellbalance.com');

        $this->assertSame('test@wellbalance.com', $user->getUserIdentifier());
    }

    public function testRolesCanBeSet(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_MEDECIN']);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_MEDECIN', $roles);
    }
}

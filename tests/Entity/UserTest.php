<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        // Test case 1: With existing roles
        $user1 = new User();
        $user1->setRoles(['ROLE_ADMIN']);
        $roles1 = $user1->getRoles();
        
        $this->assertContains('ROLE_USER', $roles1);
        $this->assertContains('ROLE_ADMIN', $roles1);
        
        // Test case 2: With no roles (edge case)
        $user2 = new User();
        $user2->setRoles([]);
        $roles2 = $user2->getRoles();
        
        $this->assertContains('ROLE_USER', $roles2);
        $this->assertCount(1, $roles2);
        
        // Test case 3: With multiple roles
        $user3 = new User();
        $user3->setRoles(['ROLE_ADMIN', 'ROLE_EDITOR']);
        $roles3 = $user3->getRoles();
        
        $this->assertContains('ROLE_USER', $roles3);
        $this->assertContains('ROLE_ADMIN', $roles3);
        $this->assertContains('ROLE_EDITOR', $roles3);
        
        // Test case 4: With duplicate roles
        $user4 = new User();
        $user4->setRoles(['ROLE_ADMIN', 'ROLE_ADMIN']);
        $roles4 = $user4->getRoles();
        
        $this->assertContains('ROLE_USER', $roles4);
        $this->assertContains('ROLE_ADMIN', $roles4);
        $this->assertCount(2, $roles4); // ROLE_USER + ROLE_ADMIN (no duplicates)
    }
    
    public function testGetUserIdentifierReturnsEmailAsString(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        
        // Act
        $identifier = $user->getUserIdentifier();
        
        // Assert
        $this->assertEquals('test@example.com', $identifier);
        $this->assertIsString($identifier);
    }
    
    public function testGetRolesRemovesDuplicates(): void
    {
        // Arrange
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        
        // Act
        $roles = $user->getRoles();
        
        // Assert
        $this->assertCount(2, $roles); // ROLE_ADMIN + ROLE_USER (no duplicates)
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }
}

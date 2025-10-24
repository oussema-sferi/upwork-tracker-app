<?php

namespace App\Tests\Entity;

use App\Entity\Alert;
use App\Entity\Job;
use PHPUnit\Framework\TestCase;

class AlertTest extends TestCase
{
    public function testConstructorSetsCreatedAtAutomatically(): void
    {
        // Arrange & Act
        $alert = new Alert();
        
        // Assert
        $this->assertInstanceOf(\DateTimeImmutable::class, $alert->getCreatedAt());
        $this->assertNotNull($alert->getCreatedAt());
        
        // Verify it was set recently (within last 5 seconds)
        $now = new \DateTimeImmutable();
        $createdAt = $alert->getCreatedAt();
        $this->assertLessThan(5, $now->getTimestamp() - $createdAt->getTimestamp());
    }
    
    public function testAlertStatusLogic(): void
    {
        // Test case 1: New alert is not sent by default
        $alert = new Alert();
        $this->assertFalse($alert->isSent());
        $this->assertNull($alert->getSentAt());
        
        // Test case 2: Mark alert as sent
        $alert->setIsSent(true);
        $this->assertTrue($alert->isSent());
        
        // Test case 3: Set sent timestamp
        $sentAt = new \DateTimeImmutable();
        $alert->setSentAt($sentAt);
        $this->assertEquals($sentAt, $alert->getSentAt());
        
        // Test case 4: Mark alert as not sent
        $alert->setIsSent(false);
        $this->assertFalse($alert->isSent());
    }
    
    public function testAlertTypeManagement(): void
    {
        // Arrange
        $alert = new Alert();
        
        // Act & Assert
        $alert->setType('email');
        $this->assertEquals('email', $alert->getType());
        
        $alert->setType('sms');
        $this->assertEquals('sms', $alert->getType());
    }
    
    public function testAlertJobRelationship(): void
    {
        // Arrange
        $alert = new Alert();
        $job = new Job();
        
        // Act
        $alert->setJob($job);
        
        // Assert
        $this->assertSame($job, $alert->getJob());
        
        // Test removing job
        $alert->setJob(null);
        $this->assertNull($alert->getJob());
    }
    
    public function testAlertGettersAndSetters(): void
    {
        // Arrange
        $alert = new Alert();
        $createdAt = new \DateTimeImmutable('2024-01-01 12:00:00');
        $sentAt = new \DateTimeImmutable('2024-01-01 13:00:00');
        
        // Act
        $alert->setCreatedAt($createdAt);
        $alert->setType('notification');
        $alert->setIsSent(true);
        $alert->setSentAt($sentAt);
        
        // Assert
        $this->assertEquals($createdAt, $alert->getCreatedAt());
        $this->assertEquals('notification', $alert->getType());
        $this->assertTrue($alert->isSent());
        $this->assertEquals($sentAt, $alert->getSentAt());
    }
}

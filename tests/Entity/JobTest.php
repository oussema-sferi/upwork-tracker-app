<?php

namespace App\Tests\Entity;

use App\Entity\Job;
use App\Entity\Alert;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    public function testConstructorInitializesAlertsCollection(): void
    {
        // Arrange & Act
        $job = new Job();
        
        // Assert
        $this->assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $job->getAlerts());
        $this->assertCount(0, $job->getAlerts());
        $this->assertTrue($job->getAlerts()->isEmpty());
    }
    
    public function testAddAlertCreatesBidirectionalRelationship(): void
    {
        // Arrange
        $job = new Job();
        $alert = new Alert();
        
        // Act
        $job->addAlert($alert);
        
        // Assert
        $this->assertCount(1, $job->getAlerts());
        $this->assertTrue($job->getAlerts()->contains($alert));
        $this->assertSame($job, $alert->getJob());
    }

    public function testAddDuplicateAlerts(): void
    {
        // Arrange
        $job = new Job();
        $alert = new Alert();
        
        // Act
        $job->addAlert($alert);
        $job->addAlert($alert);
        // Assert
        $this->assertCount(1, $job->getAlerts());
    }
    
    public function testRemoveAlertBreaksBidirectionalRelationship(): void
    {
        // Arrange
        $job = new Job();
        $alert = new Alert();
        $job->addAlert($alert);
        
        // Act
        $job->removeAlert($alert);
        
        // Assert
        $this->assertCount(0, $job->getAlerts());
        $this->assertFalse($job->getAlerts()->contains($alert));
        $this->assertNull($alert->getJob());
    }
    
    public function testRemoveNonExistentAlertIsSafe(): void
    {
        // Arrange
        $job = new Job();
        $alert = new Alert();
        
        // Act
        $job->removeAlert($alert); // Remove alert that was never added
        
        // Assert
        $this->assertCount(0, $job->getAlerts());
        $this->assertNull($alert->getJob());
    }
    
    public function testJobBasicProperties(): void
    {
        // Arrange
        $job = new Job();
        $upworkId = '12345';
        $title = 'PHP Developer Job';
        $description = 'Looking for a skilled PHP developer';
        $url = 'https://upwork.com/jobs/12345';
        $postedAt = new \DateTimeImmutable('2024-01-01 12:00:00');
        
        // Act
        $job->setUpworkId($upworkId);
        $job->setTitle($title);
        $job->setDescription($description);
        $job->setUrl($url);
        $job->setPostedAt($postedAt);
        
        // Assert
        $this->assertEquals($upworkId, $job->getUpworkId());
        $this->assertEquals($title, $job->getTitle());
        $this->assertEquals($description, $job->getDescription());
        $this->assertEquals($url, $job->getUrl());
        $this->assertEquals($postedAt, $job->getPostedAt());
    }
    
    public function testJobGettersReturnNullInitially(): void
    {
        // Arrange & Act
        $job = new Job();
        
        // Assert
        $this->assertNull($job->getId());
        $this->assertNull($job->getUpworkId());
        $this->assertNull($job->getTitle());
        $this->assertNull($job->getDescription());
        $this->assertNull($job->getUrl());
        $this->assertNull($job->getPostedAt());
    }
}
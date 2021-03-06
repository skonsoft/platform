<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Voter;

use Oro\Bundle\SecurityBundle\Acl\AccessLevel;
use Oro\Bundle\SecurityBundle\Acl\Voter\AclVoter;

class AclVoterTest extends \PHPUnit_Framework_TestCase
{
    public function testVote()
    {
        $selector = $this->getMockBuilder('Oro\Bundle\SecurityBundle\Acl\Extension\AclExtensionSelector')
            ->disableOriginalConstructor()
            ->getMock();
        $permissionMap = $this->getMock('Symfony\Component\Security\Acl\Permission\PermissionMapInterface');
        $voter = new AclVoter(
            $this->getMock('Symfony\Component\Security\Acl\Model\AclProviderInterface'),
            $this->getMock('Symfony\Component\Security\Acl\Model\ObjectIdentityRetrievalStrategyInterface'),
            $this->getMock('Symfony\Component\Security\Acl\Model\SecurityIdentityRetrievalStrategyInterface'),
            $permissionMap
        );
        $voter->setAclExtensionSelector($selector);

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $object = new \stdClass();
        $extension = $this->getMock('Oro\Bundle\SecurityBundle\Acl\Extension\AclExtensionInterface');
        $extension->expects($this->once())
            ->method('getAccessLevel')
            ->with($this->equalTo(1))
            ->will($this->returnValue(AccessLevel::LOCAL_LEVEL));

        $isGrantedObserver = $this->getMockBuilder('Oro\Bundle\SecurityBundle\Acl\Domain\OneShotIsGrantedObserver')
            ->disableOriginalConstructor()
            ->getMock();
        $voter->addOneShotIsGrantedObserver($isGrantedObserver);

        $isGrantedObserver->expects($this->once())
            ->method('setAccessLevel')
            ->with($this->equalTo(AccessLevel::LOCAL_LEVEL));

        $selector->expects($this->exactly(2))
            ->method('select')
            ->with($this->identicalTo($object))
            ->will($this->returnValue($extension));

        $inVoteToken = null;
        $inVoteObject = null;
        $inVoteExtension = null;

        $permissionMap->expects($this->exactly(2))
            ->method('getMasks')
            ->will(
                $this->returnCallback(
                    function () use (&$voter, &$inVoteToken, &$inVoteObject, &$inVoteExtension) {
                        $inVoteToken = $voter->getSecurityToken();
                        $inVoteObject = $voter->getObject();
                        $inVoteExtension = $voter->getAclExtension();
                        $voter->setTriggeredMask(1);

                        return null;
                    }
                )
            );

        $this->assertNull($voter->getSecurityToken());
        $this->assertNull($voter->getObject());
        $this->assertNull($voter->getAclExtension());

        $voter->vote($token, $object, array('test'));

        $this->assertNull($voter->getSecurityToken());
        $this->assertNull($voter->getObject());
        $this->assertNull($voter->getAclExtension());

        $this->assertTrue($token === $inVoteToken);
        $this->assertTrue($object === $inVoteObject);
        $this->assertTrue($extension === $inVoteExtension);

        // call the vote method one more time to ensure that OneShotIsGrantedObserver was removed from the voter
        $voter->vote($token, $object, array('test'));
    }
}

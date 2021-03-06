<?php

namespace Oro\Bundle\CronBundle\Command;

use Doctrine\ORM\EntityManager;

use JMS\JobQueueBundle\Entity\Job;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Oro\Bundle\CronBundle\Tests\Unit\Stub\MemoryOutput;
use Oro\Bundle\TestFrameworkBundle\Test\Doctrine\ORM\OrmTestCase;

class CleanupCommandTest extends OrmTestCase
{
    /** @var CleanupCommand */
    protected $command;

    /** @var ContainerInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $container;

    /** @var EntityManager|\PHPUnit_Framework_MockObject_MockObject */
    protected $emMock;

    public function setUp()
    {
        $this->command = new CleanupCommand();

        $this->container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $this->command->setContainer($this->container);

        $this->emMock = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()->getMock();
    }

    public function tearDown()
    {
        unset($this->container, $this->command, $this->emMock);
    }

    public function testConfiguration()
    {
        $this->command->configure();

        $this->assertNotEmpty($this->command->getDescription());
        $this->assertNotEmpty($this->command->getName());
        $this->assertTrue($this->command->getDefinition()->hasOption('dry-run'));
    }

    public function testIsValidCronCommand()
    {
        $this->assertInstanceOf('Oro\Bundle\CronBundle\Command\CronCommandInterface', $this->command);

        $this->assertContains('oro:cron:', $this->command->getName(), 'name should start with oro:cron');
        $this->assertInternalType('string', $this->command->getDefaultDefinition());
    }

    public function testDryExecution()
    {
        $params    = ['-d' => true];
        $input     = new ArrayInput($params, $this->command->getDefinition());
        $output    = new MemoryOutput();

        $queryMock = $this->getMockBuilder('Doctrine\ORM\AbstractQuery')
            ->disableOriginalConstructor()
            ->setMethods(['getSingleScalarResult'])
            ->getMockForAbstractClass();

        $queryMock->expects($this->once())->method('getSingleScalarResult')
            ->will($this->returnValue('0'));

        $qbMock = $this->getQueryBuilderMock();
        $qbMock->expects($this->once())
            ->method('select')
            ->with('COUNT(j.id)')
            ->will($this->returnSelf());

        $qbMock->expects($this->once())
            ->method('getQuery')
            ->will($this->returnValue($queryMock));

        $this->command->execute($input, $output);
    }

    public function testExecution()
    {
        $this->container->expects($this->once())
            ->method('get')
            ->with('doctrine.orm.entity_manager')
            ->will($this->returnValue($this->emMock));

        $qbMock = $this->getQueryBuilderMock();
        $this->emMock->expects($this->once())
            ->method('createQueryBuilder')
            ->will($this->returnValue($qbMock));

        $queryMock = $this->getMockBuilder('Doctrine\ORM\AbstractQuery')
            ->disableOriginalConstructor()
            ->setMethods(['getResult'])
            ->getMockForAbstractClass();

        $jobs = [
            new Job('test'),
            new Job('test-deps'),
        ];

        $queryMock->expects($this->once())->method('getResult')
            ->will($this->returnValue($jobs));

        $qbMock->expects($this->once())
            ->method('select')
            ->with('j')
            ->will($this->returnSelf());

        $qbMock->expects($this->once())
            ->method('getQuery')
            ->will($this->returnValue($queryMock));


        $countQuery = $this->getMockBuilder('Doctrine\ORM\AbstractQuery')
            ->disableOriginalConstructor()
            ->setMethods(['setParameter', 'getSingleScalarResult'])
            ->getMockForAbstractClass();

        $countQuery->expects($this->at(0))
            ->method('setParameter')
            ->will($this->returnSelf());
        $countQuery->expects($this->at(1))
            ->method('getSingleScalarResult')
            ->will($this->returnValue(0));
        $countQuery->expects($this->at(2))
            ->method('setParameter')
            ->will($this->returnSelf());
        $countQuery->expects($this->at(3))
            ->method('getSingleScalarResult')
            ->will($this->returnValue(1));

        $this->emMock->expects($this->exactly(2))
            ->method('createQuery')
            ->will($this->returnValue($countQuery));

        $this->emMock->expects($this->once())
            ->method('remove');

        $this->emMock->expects($this->once())
            ->method('flush');

        $conn = $this->getMockBuilder('\Doctrine\DBAL\Connection')
            ->disableOriginalConstructor()
            ->setMethods(['executeUpdate'])
            ->getMock();
        $conn->expects($this->exactly(2))
            ->method('executeUpdate');
        $this->emMock->expects($this->once())
            ->method('getConnection')
            ->will($this->returnValue($conn));

        $params    = [];
        $input     = new ArrayInput($params, $this->command->getDefinition());
        $output    = new MemoryOutput();

        $this->command->execute($input, $output);
    }

    protected function getQueryBuilderMock()
    {
        $qbMock = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $qbMock->expects($this->any())
            ->method('from')
            ->will($this->returnSelf());

        $qbMock->expects($this->any())
            ->method('where')
            ->will($this->returnSelf());

        $qbMock->expects($this->any())
            ->method('andWhere')
            ->will($this->returnSelf());

        $qbMock->expects($this->any())
            ->method('setParameters')
            ->will($this->returnSelf());


        $this->emMock->expects($this->any())
            ->method('createQueryBuilder')
            ->will($this->returnValue($qbMock));


        $this->container->expects($this->once())
            ->method('get')
            ->with('doctrine.orm.entity_manager')
            ->will($this->returnValue($this->emMock));

        return $qbMock;
    }
}

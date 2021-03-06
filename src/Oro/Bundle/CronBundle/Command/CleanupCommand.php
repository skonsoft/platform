<?php

namespace Oro\Bundle\CronBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\QueryBuilder;

use JMS\JobQueueBundle\Entity\Job;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Oro\Bundle\CronBundle\Command\Logger\OutputLogger;

class CleanupCommand extends ContainerAwareCommand implements CronCommandInterface
{
    const COMMAND_NAME = 'oro:cron:cleanup';

    /**
     * {@inheritdoc}
     */
    public function getDefaultDefinition()
    {
        return '7 0 * * *'; // every day
    }

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName(static::COMMAND_NAME)
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'If option exists items won\'t be deleted, items count that match cleanup criteria will be shown'
            )
            ->setDescription('Clear cron-related log-alike tables: queue, etc');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new OutputLogger($output);
        $em = $this->getContainer()
            ->get('doctrine.orm.entity_manager');
        $qb = $em->createQueryBuilder();

        if ($input->getOption('dry-run')) {
            $result = $this
                ->applyCriteria($qb->select('COUNT(j.id)'))
                ->getQuery()
                ->getSingleScalarResult();

            $message = 'Will be removed %d rows';
        } else {
            $query = $this->applyCriteria($qb->select('j'))
                ->getQuery();

            $jobs = $query->getResult();

            $result = 0;
            $jobIds = [];
            foreach ($jobs as $job) {
                /** @var Job $job */

                $incomingDepsCount = (integer)$em->createQuery(
                    "SELECT COUNT(j) FROM JMSJobQueueBundle:Job j WHERE :job MEMBER OF j.dependencies"
                )
                    ->setParameter('job', $job)
                    ->getSingleScalarResult();

                if ($incomingDepsCount > 0) {
                    continue;
                }

                $jobIds[] = $job->getId();
                $em->remove($job);
                $result++;
            }

            if ($result > 0) {
                $em->flush();

                $con = $em->getConnection();
                $con->executeUpdate(
                    "DELETE FROM jms_job_statistics WHERE job_id IN (?)",
                    [$jobIds],
                    [Connection::PARAM_INT_ARRAY]
                );

                $con->executeUpdate(
                    "DELETE FROM jms_job_dependencies WHERE source_job_id IN (?)",
                    [$jobIds],
                    [Connection::PARAM_INT_ARRAY]
                );
            }

            $message = 'Removed %d rows';
        }

        $logger->notice(sprintf($message, $result));
        $logger->notice('Completed');

        return 0;
    }

    /**
     * Remove job queue finished jobs older than $days
     *
     * @param QueryBuilder $qb
     * @param int          $days
     *
     * @return QueryBuilder
     */
    protected function applyCriteria(QueryBuilder $qb, $days = 1)
    {
        $date = new \DateTime(sprintf('%d days ago', $days), new \DateTimeZone('UTC'));
        $date = $date->format('Y-m-d H:i:s');

        $qb->from('JMSJobQueueBundle:Job', 'j')
            ->where('j.closedAt < ?0')
            ->andWhere('j.state = ?1')
            ->setParameters([$date, Job::STATE_FINISHED]);

        return $qb;
    }
}

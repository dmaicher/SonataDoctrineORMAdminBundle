<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\DoctrineORMAdminBundle\Datagrid;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Sonata\AdminBundle\Datagrid\Pager as BasePager;

/**
 * Doctrine pager class.
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 *
 * @phpstan-extends BasePager<ProxyQueryInterface>
 */
final class Pager extends BasePager
{
    /**
     * @var int
     */
    private $resultsCount = 0;

    public function getCurrentPageResults(): iterable
    {
        $query = $this->getQuery();
        if (!$query instanceof ProxyQueryInterface) {
            throw new \TypeError(sprintf(
                'The pager query MUST implement %s.',
                ProxyQueryInterface::class,
            ));
        }

        $identifierFieldNames = $query
            ->getQueryBuilder()
            ->getEntityManager()
            ->getMetadataFactory()
            ->getMetadataFor(current($query->getQueryBuilder()->getRootEntities()))
            ->getIdentifierFieldNames();

        // NEXT_MAJOR: Remove the check and the else part.
        if (method_exists($query, 'getDoctrineQuery')) {
            // Paginator with fetchJoinCollection doesn't work with composite primary keys
            // https://github.com/doctrine/orm/issues/2910
            $paginator = new Paginator($query->getDoctrineQuery(), 1 === \count($identifierFieldNames));
        } else {
            $paginator = new Paginator($query->getQueryBuilder(), 1 === \count($identifierFieldNames));
        }

        return $paginator->getIterator();
    }

    public function countResults(): int
    {
        return $this->resultsCount;
    }

    public function init(): void
    {
        $this->resultsCount = $this->computeResultsCount();

        $this->getQuery()->setFirstResult(null);
        $this->getQuery()->setMaxResults(null);

        if (0 === $this->getPage() || 0 === $this->getMaxPerPage() || 0 === $this->countResults()) {
            $this->setLastPage(0);
        } else {
            $offset = ($this->getPage() - 1) * $this->getMaxPerPage();

            $this->setLastPage((int) ceil($this->countResults() / $this->getMaxPerPage()));

            $this->getQuery()->setFirstResult($offset);
            $this->getQuery()->setMaxResults($this->getMaxPerPage());
        }
    }

    private function computeResultsCount(): int
    {
        $query = $this->getQuery();

        if (!$query instanceof ProxyQueryInterface) {
            throw new \TypeError(sprintf('The pager query MUST implement %s.', ProxyQueryInterface::class));
        }

        // NEXT_MAJOR: Remove the check and the else part.
        if (method_exists($query, 'getDoctrineQuery')) {
            $paginator = new Paginator($query->getDoctrineQuery());
        } else {
            $paginator = new Paginator($query->getQueryBuilder());
        }

        return \count($paginator);
    }
}

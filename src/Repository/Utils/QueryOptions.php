<?php

namespace App\Repository\Utils;

use Doctrine\ORM\QueryBuilder;

trait QueryOptions
{
    public function setLimit(QueryBuilder $queryBuilder, ?int $limit = null): void
    {
        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }
    }

    public function setOrder(QueryBuilder $queryBuilder, array $order = []): void
    {
        if (!empty($order)) {
            foreach ($order as $field => $order) {
                $queryBuilder->addOrderBy($field, $order);
            }
        }
    }

    /**
     * TODO Use filter method only
     */
    protected function addFilter(QueryBuilder $queryBuilder, array $search = []): void
    {
        if (!empty($search)) {
            foreach ($search as $field => $value) {
                $searchMethod = $field.'Filter';
                $this->$searchMethod($queryBuilder, $value);
            }
        }
    }

    protected function filter(QueryBuilder $queryBuilder, array $search): void
    {
        if (!empty($search)) {
            $this->queryBuilder = $queryBuilder;

            foreach ($search as $method => $filter) {
                if ($filter !== '' && $method !== '_token') {
                    $this->$method($filter);
                }
            }
        }
    }
}

<?php
namespace Lemon\RestBundle\Object\Repository;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadataInfo;
use Doctrine\ODM\MongoDB\Query\Builder;
use Lemon\RestBundle\Object\Repository;
use Lemon\RestBundle\Object\Criteria;

class MongoRepositoryWrapper implements Repository
{
    /**
     * @var ObjectRepository
     */
    protected $repository;

    /**
     * @var ClassMetadata
     */
    protected $metadata;

    /**
     * @param ObjectRepository $repository
     */
    public function __construct(ObjectRepository $repository)
    {
        $this->repository = $repository;
        $this->metadata = $repository->getClassMetadata();
    }

    public function count(Criteria $criteria)
    {
        $qb = $this->repository->createQueryBuilder();

        $this->buildWhereClause($qb, $criteria);

        $qb->select();

        return $qb->getQuery()
            ->execute()
            ->count();
    }

    /**
     * @param Criteria $criteria
     * @return array
     */
    public function search(Criteria $criteria)
    {
        $qb = $this->repository->createQueryBuilder();

        $this->buildWhereClause($qb, $criteria);

        $qb->select();

        if ($criteria->getOrderBy()) {
            $qb->sort($criteria->getOrderBy(), $criteria->getOrderDir());
        }

        if ($criteria->getOffset()) {
            $qb->skip($criteria->getOffset());
        }

        if ($criteria->getLimit()) {
            $qb->limit($criteria->getLimit());
        }

        $cursor = $qb->getQuery()
            ->execute();

        $results = array();

        foreach ($cursor as $value) {
            $results[] = $value;
        }

        return $results;
    }

    /**
     * @param string $field
     * @param string $value
     * @return array
     */
    protected function convertConditionForDBRefSupport($field, $value)
    {
        $key = $field . '.$id';
        $value = new \MongoId($value);
        return [$key, $value];
    }

    /**
     * @param QueryBuilder $qb
     * @param Criteria $criteria
     */
    protected function buildWhereClause(Builder $qb, Criteria $criteria)
    {
        foreach ($criteria as $key => $value) {
            if ($this->metadata->hasField($key) || $this->metadata->hasAssociation($key)) {
                $type = $this->metadata->fieldMappings[$key];
                //if field is reference
                if (isset($type['reference']) && $type['reference']) {
                    // if fields stored as DBRef
                    $isMongoRef = isset($type['storeAs']) && in_array(
                            $type['storeAs'],
                            [
                                ClassMetadataInfo::REFERENCE_STORE_AS_DB_REF,
                                ClassMetadataInfo::REFERENCE_STORE_AS_DB_REF_WITH_DB
                            ]
                        );

                    // Support old-syntax and new-syntax both
                    $isMongoRef = $isMongoRef || (isset($type['simple']) && !$type['simple']);
                    if ($isMongoRef) {
                        list($key, $value) = $this->convertConditionForDBRefSupport($key, $value);
                    }
                } elseif (is_numeric($value)) {
                    $value = (float)$value;
                }

                $qb->field($key)->equals($value);
            }
        }
    }

    public function findById($id)
    {
        return $this->repository->findOneBy(array('id' => $id));
    }
}

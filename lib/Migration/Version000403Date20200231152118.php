<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000403Date20200231152118 extends SimpleMigrationStep {

    /** @var IDBConnection */
    private $connection;

    /**
     * @param IDBConnection $connection
     */
    public function __construct(IDBConnection $connection) {
        $this->connection = $connection;
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $table = $schema->getTable('cospend_projects');
        $table->addColumn('guestaccesslevel', 'integer', [
            'notnull' => true,
            'length' => 4,
            'default' => 2
        ]);
        $table = $schema->getTable('cospend_shares');
        $table->addColumn('accesslevel', 'integer', [
            'notnull' => true,
            'length' => 4,
            'default' => 2
        ]);
        return $schema;
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
        $qb = $this->connection->getQueryBuilder();

        // permissions were c e d => v p m a for viewer participant maintener admin
        // user share permissions
        $qb->update('cospend_shares')
           ->set('accesslevel', $qb->createNamedParameter(2, IQueryBuilder::PARAM_INT))
           ->where(
               $qb->expr()->neq('permissions', $qb->createNamedParameter('', IQueryBuilder::PARAM_STR))
           );
        $qb->execute();
        $qb = $qb->resetQueryParts();

        $qb->update('cospend_shares')
           ->set('accesslevel', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
           ->where(
               $qb->expr()->eq('permissions', $qb->createNamedParameter('', IQueryBuilder::PARAM_STR))
           );
        $qb->execute();
        $qb = $qb->resetQueryParts();

        // guest permissions
        $qb->update('cospend_projects')
           ->set('guestaccesslevel', $qb->createNamedParameter(2, IQueryBuilder::PARAM_INT))
           ->where(
               $qb->expr()->neq('guestpermissions', $qb->createNamedParameter('', IQueryBuilder::PARAM_STR))
           );
        $qb->execute();
        $qb = $qb->resetQueryParts();

        $qb->update('cospend_projects')
           ->set('guestaccesslevel', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
           ->where(
               $qb->expr()->eq('guestpermissions', $qb->createNamedParameter('', IQueryBuilder::PARAM_STR))
           );
        $qb->execute();
        $qb = $qb->resetQueryParts();
    }
}

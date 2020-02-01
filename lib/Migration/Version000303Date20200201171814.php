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
class Version000303Date20200201171814 extends SimpleMigrationStep {

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

        if ($schema->hasTable('cospend_shares')) {
            $table = $schema->getTable('cospend_shares');
            $table->addColumn('type', 'string', [
                'notnull' => true,
                'length' => 1,
                'default' => 'u'
            ]);
        }

        return $schema;
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
        $qb = $this->connection->getQueryBuilder();
        $qb->update('cospend_shares')
           ->set('type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
           ->where(
               $qb->expr()->eq('isgroupshare', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
           );
       $qb->execute();
    }
}

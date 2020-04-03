<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000405Date20200403161849 extends SimpleMigrationStep {

    /** @var IDBConnection */
    private $connection;
    private $trans;

    /**
     * @param IDBConnection $connection
     */
    public function __construct(IDBConnection $connection, IL10N $l10n) {
        $this->connection = $connection;
        $this->trans = $l10n;
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
        return $schema;
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
        $qb = $this->connection->getQueryBuilder();

        $categoryNames = [
            '-1' => $this->trans->t('Grocery'),
            '-2' => $this->trans->t('Bar/Party'),
            '-3' => $this->trans->t('Rent'),
            '-4' => $this->trans->t('Bill'),
            '-5' => $this->trans->t('Excursion/Culture'),
            '-6' => $this->trans->t('Health'),
            '-10' => $this->trans->t('Shopping'),
            //'-11' => $this->trans->t('Reimbursement'),
            '-12' => $this->trans->t('Restaurant'),
            '-13' => $this->trans->t('Accommodation'),
            '-14' => $this->trans->t('Transport'),
            '-15' => $this->trans->t('Sport')
        ];
        $categoryIcons = [
            '-1'  => '🛒',
            '-2'  => '🎉',
            '-3'  => '🏠',
            '-4'  => '🌩',
            '-5'  => '🚸',
            '-6'  => '💚',
            '-10' => '🛍',
            //'-11' => '💰',
            '-12' => '🍴',
            '-13' => '🛌',
            '-14' => '🚌',
            '-15' => '🎾'
        ];
        $categoryColors = [
            '-1'  => '#ffaa00',
            '-2'  => '#aa55ff',
            '-3'  => '#da8733',
            '-4'  => '#4aa6b0',
            '-5'  => '#0055ff',
            '-6'  => '#bf090c',
            '-10' => '#e167d1',
            //'-11' => '#e1d85a',
            '-12' => '#d0d5e1',
            '-13' => '#5de1a3',
            '-14' => '#6f2ee1',
            '-15' => '#69e177'
        ];
        $ts = (new \DateTime())->getTimestamp();

        // get project ids
        $projectIdList = [];
        $qb->select('p.id')
           ->from('cospend_projects', 'p');
        $req = $qb->execute();

        while ($row = $req->fetch()) {
            array_push($projectIdList, $row['id']);
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        foreach ($projectIdList as $projectId) {
            foreach ($categoryNames as $strId => $name) {
                $icon = $categoryIcons[$strId];
                $color = $categoryColors[$strId];
                // insert new standard category
                $qb->insert('cospend_project_categories')
                    ->values([
                        'projectid' => $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR),
                        'icon' => $qb->createNamedParameter($icon, IQueryBuilder::PARAM_STR),
                        'color' => $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR),
                        'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)
                    ]);
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();
                $insertedCategoryId = intval($qb->getLastInsertId());

                // convert category ids in existing bills
                $qb->update('cospend_bills')
                ->set('categoryid', $qb->createNamedParameter($insertedCategoryId, IQueryBuilder::PARAM_INT))
                ->set('lastchanged', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT))
                ->where(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
                )
                ->andWhere(
                    $qb->expr()->eq('categoryid', $qb->createNamedParameter(intval($strId), IQueryBuilder::PARAM_INT))
                );
                $qb->execute();
                $qb = $qb->resetQueryParts();
            }

        }
    }
}

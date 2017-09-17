<?php
namespace NamelessCoder\InlineFalFix;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class DataHandlerHook
 */
class DataHandlerHook
{
    /**
     * Command post processing method
     *
     * Like other pre/post methods this method calls the corresponding
     * method on Providers which match the table/id(record) being passed.
     *
     * In addition, this method also listens for paste commands executed
     * via the TYPO3 clipboard, since such methods do not necessarily
     * trigger the "normal" record move hooks (which we also subscribe
     * to and react to in moveRecord_* methods).
     *
     * @param string $command The TCEmain operation status, fx. 'update'
     * @param string $table The table TCEmain is currently processing
     * @param string $id The records id (if any)
     * @param string $relativeTo Filled if command is relative to another element
     * @param DataHandler $reference Reference to the parent object (TCEmain)
     * @param array $pasteUpdate
     * @param array $pasteDataMap
     * @return void
     */
    public function processCmdmap_postProcess(&$command, $table, $id, &$relativeTo, &$reference, &$pasteUpdate, &$pasteDataMap)
    {
        if ($GLOBALS['BE_USER']->workspace && $command === 'copy') {
            // Scan all commands that were dispatched, parsing TCA to find records belonging to a table that is the target of IRRE relations.
            // On those records, load the placeholders and forcibly copy the relationship values to the draft version.
            foreach ($reference->copyMappingArray as $copiedTable => $records) {
                if (!$this->doesRecordComeFromWorkspaceEnabledTable($copiedTable)) {
                    continue;
                }
                foreach ($records as $originalUid => $copiedUid) {
                    $fixed = false;
                    $flexFields = $this->getFlexFieldsForTable($copiedTable);
                    foreach ($flexFields as $field) {
                        $record = BackendUtility::getRecord($copiedTable, $copiedUid);
                        if (!$record) {
                            // Record was deleted - no need to try repairing relations.
                            continue;
                        }
                        $relationFields = $this->getInlineRelationFieldsFromFlexFormDataStructure($copiedTable, $field, $record);
                        foreach ($relationFields as $configuration) {
                            $matchFields = [
                                $configuration['foreign_field'] => $copiedUid
                            ];
                            if ($configuration['foreign_table_field'] ?? false) {
                                $matchFields[$configuration['foreign_table_field']] = $copiedTable;
                            }
                            foreach ($configuration['foreign_match_fields'] ?? [] as $matchFieldName => $matchFieldValue) {
                                $matchFields[$matchFieldName] = $matchFieldValue;
                            }

                            $fixed = $this->fixRelationValuesForRelatedRecords($configuration['foreign_table'], $copiedUid, $matchFields) || $fixed;
                        }
                    }
                    if ($fixed) {
                        $this->fixFlexFormSourceInRecord($copiedTable, $copiedUid, $flexFields);
                    }
                }
            }
        }
    }

    /**
     * @param string $table
     * @return bool
     */
    protected function doesRecordComeFromWorkspaceEnabledTable($table)
    {
        return BackendUtility::isTableWorkspaceEnabled($table);
    }

    /**
     * @param string $table
     * @return array
     */
    protected function getFlexFieldsForTable($table)
    {
        $fields = [];
        foreach ($GLOBALS['TCA'][$table]['columns'] as $fieldName => $column) {
            if ($column['config']['type'] === 'flex') {
                $fields[] = $fieldName;
            }
        }
        return $fields;
    }

    /**
     * @param string $table
     * @param string $field
     * @param array $record
     * @return array
     */
    protected function getInlineRelationFieldsFromFlexFormDataStructure($table, $field, array $record)
    {
        $flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
        $dataSourceIdentifier = $flexFormTools->getDataStructureIdentifier(
            $GLOBALS['TCA'][$table]['columns'][$field],
            $table,
            $field,
            $record
        );

        $dataSource = $flexFormTools->parseDataStructureByIdentifier($dataSourceIdentifier);
        return $this->extractInlineRelationFieldsFromDataStructureRecursive($dataSource);
    }

    /**
     * @param array $structure
     * @return array
     */
    protected function extractInlineRelationFieldsFromDataStructureRecursive(array $structure)
    {
        $fields = [];
        foreach ($structure as $name => $value) {
            if (($value['TCEforms']['config']['type'] ?? null) === 'inline') {
                $fields[$name] = $value['TCEforms']['config'];
            } elseif (is_array($value)) {
                $fields += $this->extractInlineRelationFieldsFromDataStructureRecursive($value);
            }

        }
        return $fields;
    }

    /**
     * @param string $table
     * @param integer $copiedUid
     * @param array $matchFields
     * @return bool
     */
    protected function fixRelationValuesForRelatedRecords($table, $copiedUid, array $matchFields)
    {
        $affectedRecords = 0;
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->select(...array_merge(array_keys($matchFields), ['uid']))->from($table);
        $predicates = [];
        foreach ($matchFields as $fieldName => $fieldValue) {
            $predicates[] = $queryBuilder->expr()->eq($fieldName, $queryBuilder->quote($fieldValue));
        }
        $queryBuilder->andWhere(...$predicates);
        $relatedRecords = $queryBuilder->execute()->fetchAll();
        foreach ($relatedRecords as $relatedRecord) {
            // Update the versioned record to force all match-field values to the same as $relatedRecord
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $queryBuilder->getRestrictions()->removeAll();
            $predicates = [
                $queryBuilder->expr()->eq('t3ver_oid', $relatedRecord['uid']),
                $queryBuilder->expr()->eq('pid', -1),
                $queryBuilder->expr()->eq('t3ver_wsid', $GLOBALS['BE_USER']->workspace)
            ];
            unset($relatedRecord['uid']);
            $queryBuilder->update($table)->andWhere(...$predicates);
            foreach ($relatedRecord as $fieldName => $fieldValue) {
                $queryBuilder->set($fieldName, $fieldValue, true);
            }
            $affectedRecords += $queryBuilder->execute();
        }
        return $affectedRecords > 0;
    }

    /**
     * @param string $table
     * @param integer $id
     * @param array $fields
     */
    protected function fixFlexFormSourceInRecord($table, $id, array $fields)
    {
        $record = BackendUtility::getRecord($table, $id);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $predicates = [
            $queryBuilder->expr()->eq('t3ver_oid', $id),
            $queryBuilder->expr()->eq('pid', -1),
            $queryBuilder->expr()->eq('t3ver_wsid', $GLOBALS['BE_USER']->workspace)
        ];
        $queryBuilder->update($table)->andWhere(...$predicates);
        foreach ($fields as $fieldName) {
            $queryBuilder->set($fieldName, $record[$fieldName], true);
        }
        $queryBuilder->execute();
    }
}
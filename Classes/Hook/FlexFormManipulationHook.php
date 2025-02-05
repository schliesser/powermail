<?php
declare(strict_types = 1);
namespace In2code\Powermail\Hook;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Class FlexFormManipulationHook
 */
class FlexFormManipulationHook
{
    /**
     * @var array
     */
    protected array $allowedSheets = [
        'main',
        'receiver',
        'sender',
        'thx'
    ];

    /**
     * Add new FlexForm Field at the end of a sheet
     *
     *  Example page TSConfig:
     *      tx_powermail.flexForm.addField {
     *           settings\.flexform\.main\.test._sheet = main
     *           settings\.flexform\.main\.test.label = LLL:EXT:ext/path/locallang_db.xlf:flexform.main.form
     *           settings\.flexform\.main\.test.config.type = input
     *           settings\.flexform\.main\.test.config.eval = trim
     *      }
     *
     *  Will be available in Templates with {settings.main.test}
     *
     * @param array $dataStructArray FlexForm DataStructure
     * @param array $configuration
     * @param array $row table properties
     * @param string $table tableName
     * @param string $fieldName fieldName
     * @return void
     */
    public function getFlexFormDS_postProcessDS(
        array &$dataStructArray,
        array $configuration,
        array $row,
        string $table,
        string $fieldName
    ): void {
        unset($configuration, $fieldName);
        if ($this->isPowermailFlexForm($table, $row)) {
            foreach ($this->getFieldConfiguration((int)$row['pid']) as $key => $fieldConfiguration) {
                $sheet = $this->getSheetNameAndRemoveFromConfiguration($fieldConfiguration);
                $dataStructArray['sheets'][$sheet]['ROOT']['el'][$key]['TCEforms'] = $fieldConfiguration;
            }
        }
    }

    /**
     * Add $row['pid'] to tt_content powermail data structure identifiers to help data
     * structure hook to find page ts.
     *
     * @param array $fieldTca
     * @param string $tableName
     * @param string $fieldName
     * @param array $row
     * @param array $identifier
     * @return array $identifier Modified identifier
     */
    public function getDataStructureIdentifierPostProcess(
        array $fieldTca,
        string $tableName,
        string $fieldName,
        array $row,
        array $identifier
    ): array {
        unset($fieldTca);
        if ($tableName === 'tt_content' && $fieldName === 'pi_flexform'
            && $row['CType'] === 'list' && $row['list_type'] === 'powermail_pi1') {
            // Add pid to identifier to fetch pageTs in parseDataStructureByIdentifierPostProcess hook
            $identifier['pid'] = $row['pid'];
        }
        return $identifier;
    }

    /**
     * Add new FlexForm Field at the end of a sheet
     *
     * Core >= 8.5 version of the hook.
     *
     * Example page TSConfig:
     *     tx_powermail.flexForm.addField {
     *          settings\.flexform\.main\.test._sheet = main
     *          settings\.flexform\.main\.test.label = LLL:EXT:ext/path/locallang_db.xlf:flexform.main.form
     *          settings\.flexform\.main\.test.config.type = input
     *          settings\.flexform\.main\.test.config.eval = trim
     *     }
     *
     * Will be available in Templates with {settings.main.test}
     *
     * @param array $dataStructure
     * @param array $identifier
     * @return array Modified data structure
     */
    public function parseDataStructureByIdentifierPostProcess(array $dataStructure, array $identifier): array
    {
        if ($identifier['type'] === 'tca'
            && $identifier['tableName'] === 'tt_content'
            && $identifier['fieldName'] === 'pi_flexform'
            && $identifier['dataStructureKey'] === 'powermail_pi1,list'
            && !empty($identifier['pid'])
            && MathUtility::canBeInterpretedAsInteger($identifier['pid'])
        ) {
            foreach ($this->getFieldConfiguration((int)$identifier['pid']) as $key => $fieldConfiguration) {
                $sheet = $this->getSheetNameAndRemoveFromConfiguration($fieldConfiguration);
                $dataStructure['sheets'][$sheet]['ROOT']['el'][$key]['TCEforms'] = $fieldConfiguration;
            }
        }
        return $dataStructure;
    }

    /**
     * Get field configuration from page TSconfig
     *
     * @param int $pid Record pid
     * @return array
     */
    protected function getFieldConfiguration(int $pid): array
    {
        $tsConfiguration = BackendUtility::getPagesTSconfig((int)$pid);
        if (!empty($tsConfiguration['tx_powermail.']['flexForm.']['addField.'])) {
            $eConfiguration = $tsConfiguration['tx_powermail.']['flexForm.']['addField.'];
            $tsService = GeneralUtility::makeInstance(TypoScriptService::class);
            return $tsService->convertTypoScriptArrayToPlainArray($eConfiguration);
        }
        return [];
    }

    /**
     * Get sheetname and remove from configuration array
     *
     * @param array $configuration
     * @return string
     */
    protected function getSheetNameAndRemoveFromConfiguration(array &$configuration): string
    {
        $sheet = $this->allowedSheets[0];
        if (!empty($configuration['_sheet']) && in_array($configuration['_sheet'], $this->allowedSheets)) {
            $sheet = $configuration['_sheet'];
        }
        unset($configuration['_sheet']);
        return $sheet;
    }

    /**
     * Check if this flexform is loaded in powermail Pi1 context
     *
     * @param string $table Table name
     * @param array $row
     * @return bool
     */
    protected function isPowermailFlexForm(string $table, array $row): bool
    {
        return $table === 'tt_content' && $row['CType'] === 'list'
            && $row['list_type'] === 'powermail_pi1';
    }
}

<?php

declare(strict_types=1);

namespace Extrameile\FormDynamicRecipient\Hooks;

use Extrameile\FormDynamicRecipient\Domain\Model\Recipient;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Validation\Error;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Service\TranslationService;

class FormElementsOnSubmitHooks
{
    /**
     * @param \TYPO3\CMS\Form\Domain\Runtime\FormRuntime $formRuntime
     * @param \TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface $renderable
     * @param $elementValue
     * @param array $requestArguments
     * @return mixed
     * @throws \Exception
     */
    public function afterSubmit(FormRuntime $formRuntime, RenderableInterface $renderable, $elementValue, array $requestArguments = [])
    {
        /** @var \Extrameile\FormDynamicRecipient\Domain\Model\FormElements\SelectableRecipientOptions $renderable */
        if ($renderable->getType() === 'FormDynamicRecipient') {
            $assignedVariable = $renderable->getProperties()['assignedVariable'] ?: 'dynamicRecipient';

            $uid = (int) $elementValue;

            if ($uid > 0 && array_key_exists($uid, $renderable->getProperties()['options'])) {
                $recipient = $this->getRecipient($uid);
                
                // should not happen, since the TCA field is evaluated to email
                if (!\is_array($recipient) || !GeneralUtility::validEmail($recipient['recipient_email'])) {
                    throw new \Exception('Invalid email address for recipient detected', 1517428129);
                }
                
                $formRuntime->getFormState()->setFormValue($assignedVariable . '.email', $recipient['recipient_email']);
                $formRuntime->getFormState()->setFormValue($assignedVariable . '.label', $recipient['recipient_label']);
                // set also name as an alias for label since this is the usual name for the recipient property
                $formRuntime->getFormState()->setFormValue($assignedVariable . '.name', $recipient['recipient_label']);
            } elseif ($uid > 0 && !array_key_exists($uid, $renderable->getProperties()['options'])) {
                // throw new \Exception('Invalid value for recipient detected', 1517428129);
                $processingRule = $renderable->getRootForm()->getProcessingRule($renderable->getIdentifier());
                $processingRule->getProcessingMessages()->addError(
                    GeneralUtility::makeInstance(ObjectManager::class)
                    ->get(
                        Error::class,
                        TranslationService::getInstance()->translate('validation.error.1517428129', null, 'EXT:form_dynamic_recipient/Resources/Private/Language/locallang.xlf'),
                        1517428129
                        )
                    );
            }
        }

        return $elementValue;
    }

    /**
     * @param $uid
     * @return mixed
     * @throws \UnexpectedValueException
     */
    private function getRecipient($uid)
    {
        $row = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(Recipient::TABLE)
            ->select(
                ['*'],
                Recipient::TABLE, // from
                ['uid' => $uid], // where
                [], // group
                [], // order
                1   // limit
            )
            ->fetch();

        if ($row) {
            $GLOBALS['TSFE']->sys_page->versionOL(Recipient::TABLE, $row, true);
            // Language overlay:
            if (\is_array($row) && $GLOBALS['TSFE']->sys_language_contentOL) {
                $row = $GLOBALS['TSFE']->sys_page->getRecordOverlay(
                    Recipient::TABLE,
                    $row,
                    $GLOBALS['TSFE']->sys_language_content,
                    $GLOBALS['TSFE']->sys_language_contentOL
                );
            }
        }

        return $row;
    }
}

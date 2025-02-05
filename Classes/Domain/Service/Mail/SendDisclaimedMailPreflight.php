<?php
declare(strict_types = 1);
namespace In2code\Powermail\Domain\Service\Mail;

use In2code\Powermail\Domain\Model\Mail;
use In2code\Powermail\Domain\Repository\MailRepository;
use In2code\Powermail\Utility\ObjectUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidExtensionNameException;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException;

/**
 * Class SendDisclaimedMailPreflight
 * to notify the receiver about the disclaimed mail
 */
class SendDisclaimedMailPreflight
{

    /**
     * @var SendMailService
     */
    protected SendMailService $sendMailService;

    /**
     * @var MailRepository
     */
    protected MailRepository $mailRepository;

    /**
     * @var array
     */
    protected array $settings = [];

    /**
     * @var array
     */
    protected array $conf = [];

    /**
     * @param array $settings
     * @param array $conf
     */
    public function __construct(array $settings, array $conf)
    {
        $this->settings = $settings;
        $this->conf = $conf;
        $this->sendMailService = GeneralUtility::makeInstance(SendMailService::class);
        $this->mailRepository = GeneralUtility::makeInstance(MailRepository::class);
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws InvalidConfigurationTypeException
     * @throws InvalidExtensionNameException
     * @throws InvalidSlotException
     * @throws InvalidSlotReturnException
     * @throws Exception
     */
    public function sendMail(Mail $mail): void
    {
        $receiverService = GeneralUtility::makeInstance(
            ReceiverMailReceiverPropertiesService::class,
            $mail,
            $this->settings
        );
        $senderService = GeneralUtility::makeInstance(
            ReceiverMailSenderPropertiesService::class,
            $mail,
            $this->settings
        );
        foreach ($receiverService->getReceiverEmails() as $receiver) {
            $email = [
                'template' => 'Mail/DisclaimedNotificationMail',
                'receiverEmail' => $receiver,
                'receiverName' => $receiverService->getReceiverName(),
                'senderEmail' => $senderService->getSenderEmail(),
                'senderName' => $senderService->getSenderName(),
                'replyToEmail' => $senderService->getSenderEmail(),
                'replyToName' => $senderService->getSenderName(),
                'subject' => ObjectUtility::getContentObject()->cObjGetSingle(
                    $this->conf['disclaimer.']['subject'],
                    $this->conf['disclaimer.']['subject.']
                ),
                'rteBody' => '',
                'format' => $this->settings['sender']['mailformat'],
                'variables' => ['mail' => $mail]
            ];
            $this->sendMailService->sendMail($email, $mail, $this->settings, 'disclaimer');
        }
    }
}

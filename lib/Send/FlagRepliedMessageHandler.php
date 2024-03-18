<?php

declare(strict_types=1);
/**
 * @copyright 2024 Anna Larch <anna.larch@gmx.net>
 *
 * @author Anna Larch <anna.larch@gmx.net>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Mail\Send;

use Horde_Imap_Client;
use Horde_Imap_Client_Exception;
use OCA\Mail\Account;
use OCA\Mail\Db\LocalMessage;
use OCA\Mail\Db\MailboxMapper;
use OCA\Mail\Db\MessageMapper as DbMessageMapper;
use OCA\Mail\IMAP\IMAPClientFactory;
use OCA\Mail\IMAP\MessageMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;

class FlagRepliedMessageHandler extends AHandler {
	public function __construct(private IMAPClientFactory $imapClientFactory,
		private MailboxMapper $mailboxMapper,
		private LoggerInterface $logger,
		private MessageMapper $messageMapper,
		private DbMessageMapper $dbMessageMapper,
	) {
		parent::__construct();
	}

	public function process(Account $account, LocalMessage $localMessage): LocalMessage {
		if ($localMessage->getStatus() !== LocalMessage::STATUS_PROCESSED) {
			return $localMessage;
		}

		if ($localMessage->getInReplyToMessageId() === null) {
			return $this->processNext($account, $localMessage);
		}

		$messages = $this->dbMessageMapper->findByMessageId($account, $localMessage->getInReplyToMessageId());
		if ($messages === []) {
			return $this->processNext($account, $localMessage);
		}

		try {
			$client = $this->imapClientFactory->getClient($account);
			foreach ($messages as $message) {
				try {
					$mailbox = $this->mailboxMapper->findById($message->getMailboxId());
					//ignore read-only mailboxes
					if ($mailbox->getMyAcls() !== null && !strpos($mailbox->getMyAcls(), 'w')) {
						continue;
					}
					// ignore drafts and sent
					if ($mailbox->isSpecialUse('sent') || $mailbox->isSpecialUse('drafts')) {
						continue;
					}
					// Mark all other mailboxes that contain the message with the same imap message id as replied
					$this->messageMapper->addFlag(
						$client,
						$mailbox,
						[$message->getUid()],
						Horde_Imap_Client::FLAG_ANSWERED
					);
					$message->setFlagAnswered(true);
					$this->dbMessageMapper->update($message);
				} catch (DoesNotExistException|Horde_Imap_Client_Exception $e) {
					$this->logger->warning('Could not flag replied message: ' . $e, [
						'exception' => $e,
					]);
				}

			}
		} finally {
			$client->logout();
		}

		return $this->processNext($account, $localMessage);
	}
}

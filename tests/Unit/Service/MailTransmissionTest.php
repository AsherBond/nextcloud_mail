<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Richard Steinmetz <richard@steinmetz.cloud>
 *
 * Mail
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Mail\Tests\Unit\Service;

use ChristophWurst\Nextcloud\Testing\TestCase;
use Horde_Imap_Client_Socket;
use Horde_Mail_Transport;
use OCA\Mail\Account;
use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\Db\Alias;
use OCA\Mail\Db\LocalAttachment;
use OCA\Mail\Db\LocalMessage;
use OCA\Mail\Db\MailAccount;
use OCA\Mail\Db\Mailbox as DbMailbox;
use OCA\Mail\Db\MailboxMapper;
use OCA\Mail\Db\Message as DbMessage;
use OCA\Mail\Db\Recipient;
use OCA\Mail\Exception\ClientException;
use OCA\Mail\Exception\ServiceException;
use OCA\Mail\IMAP\IMAPClientFactory;
use OCA\Mail\IMAP\MessageMapper;
use OCA\Mail\Model\Message;
use OCA\Mail\Model\NewMessageData;
use OCA\Mail\Service\AliasesService;
use OCA\Mail\Service\MailTransmission;
use OCA\Mail\Service\TransmissionService;
use OCA\Mail\SMTP\SmtpClientFactory;
use OCA\Mail\Support\PerformanceLogger;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class MailTransmissionTest extends TestCase {
	private IMAPClientFactory|MockObject $imapClientFactory;
	private IMailManager|MockObject $mailManager;
	private SmtpClientFactory|MockObject $smtpClientFactory;
	private IEventDispatcher|MockObject $eventDispatcher;
	private MailboxMapper|MockObject $mailboxMapper;
	private MessageMapper|MockObject $messageMapper;
	private LoggerInterface|MockObject $logger;
	private PerformanceLogger|MockObject $performanceLogger;
	private MailTransmission $transmission;
	private AliasesService|MockObject $aliasService;
	private TransmissionService $transmissionService;

	protected function setUp(): void {
		parent::setUp();

		$this->imapClientFactory = $this->createMock(IMAPClientFactory::class);
		$this->smtpClientFactory = $this->createMock(SmtpClientFactory::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->mailboxMapper = $this->createMock(MailboxMapper::class);
		$this->messageMapper = $this->createMock(MessageMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->performanceLogger = $this->createMock(PerformanceLogger::class);
		$this->aliasService = $this->createMock(AliasesService::class);
		$this->transmissionService = $this->createMock(TransmissionService::class);

		$this->transmission = new MailTransmission(
			$this->imapClientFactory,
			$this->smtpClientFactory,
			$this->eventDispatcher,
			$this->mailboxMapper,
			$this->messageMapper,
			$this->logger,
			$this->performanceLogger,
			$this->aliasService,
			$this->transmissionService,
		);
	}

	public function testSendNewMessage() {
		$mailAccount = new MailAccount();
		$mailAccount->setUserId('testuser');
		$mailAccount->setSentMailboxId(123);
		/** @var Account|MockObject $account */
		$account = $this->createMock(Account::class);
		$account->method('getMailAccount')->willReturn($mailAccount);
		$account->method('getName')->willReturn('Test User');
		$account->method('getEMailAddress')->willReturn('test@user');
		$localMessage = new LocalMessage();
		$localMessage->setSubject('Test');
		$localMessage->setBody('Test');
		$localMessage->setHtml(false);

		$message = new Message();
		$transport = $this->createMock(Horde_Mail_Transport::class);

		$account->expects($this->once())
			->method('newMessage')
			->willReturn($message);
		$this->smtpClientFactory->expects($this->once())
			->method('create')
			->with($account)
			->willReturn($transport);
		$this->eventDispatcher->expects(self::exactly(2))
			->method('dispatchTyped');

		$this->transmission->sendMessage($account, $localMessage);
	}

	public function testSendNewMessageSmimeError() {
		$mailAccount = new MailAccount();
		$mailAccount->setUserId('testuser');
		$mailAccount->setSentMailboxId(123);
		/** @var Account|MockObject $account */
		$account = $this->createMock(Account::class);
		$account->method('getMailAccount')->willReturn($mailAccount);
		$account->method('getName')->willReturn('Test User');
		$account->method('getEMailAddress')->willReturn('test@user');
		$localMessage = new LocalMessage();
		$localMessage->setSubject('Test');
		$localMessage->setBody('Test');
		$localMessage->setHtml(false);

		$message = new Message();
		$transport = $this->createMock(Horde_Mail_Transport::class);

		$account->expects($this->once())
			->method('newMessage')
			->willReturn($message);
		$this->smtpClientFactory->expects($this->once())
			->method('create')
			->with($account)
			->willReturn($transport);
		$this->transmissionService->expects(self::once())
			->method('getSignMimePart')
			->willThrowException(new ServiceException());
		$this->eventDispatcher->expects(self::once())
			->method('dispatchTyped');

		$this->expectException(ServiceException::class);
		$this->transmission->sendMessage($account, $localMessage);
		$this->assertEquals(LocalMessage::STATUS_SMIME_SIGN_NO_CERT_ID, $message->getStatus());
	}

	public function testSendMessageFromAlias() {
		$mailAccount = new MailAccount();
		$mailAccount->setUserId('testuser');
		$mailAccount->setSentMailboxId(123);
		/** @var Account|MockObject $account */
		$account = $this->createMock(Account::class);
		$account->method('getMailAccount')->willReturn($mailAccount);
		$account->method('getName')->willReturn('Test User');
		$account->method('getEMailAddress')->willReturn('test@user');
		$account->method('getUserId')->willReturn('testuser');
		$alias = new Alias();
		$alias->setId(1);
		$alias->setAlias('a@d.com');
		$localMessage = new LocalMessage();
		$localMessage->setSubject('Test');
		$localMessage->setBody('Test');
		$localMessage->setHtml(false);
		$localMessage->setAliasId(1);

		$message = new Message();
		$account->expects($this->once())
			->method('newMessage')
			->willReturn($message);
		$transport = $this->createMock(Horde_Mail_Transport::class);
		$this->smtpClientFactory->expects($this->once())
			->method('create')
			->with($account)
			->willReturn($transport);
		$account->expects($this->once())
			->method('getName')
			->willReturn('User');
		$this->aliasService->expects(self::once())
			->method('find')
			->willReturn($alias);

		$this->transmission->sendMessage($account, $localMessage);
		$this->assertEquals(LocalMessage::STATUS_RAW, $localMessage->getStatus());
	}

	public function testSendNewMessageWithMessageAsAttachment() {
		$userId = 'testuser';
		$mailAccount = new MailAccount();
		$mailAccount->setUserId($userId);
		$mailAccount->setSentMailboxId(123);
		/** @var Account|MockObject $account */
		$account = $this->createMock(Account::class);
		$account->method('getMailAccount')->willReturn($mailAccount);
		$account->method('getName')->willReturn('Test User');
		$account->method('getEMailAddress')->willReturn('test@user');
		$account->method('getUserId')->willReturn($userId);
		$localMessage = new LocalMessage();
		$localMessage->setSubject('Test');
		$localMessage->setBody('Test');
		$localMessage->setHtml(false);
		$attachment = new LocalAttachment();
		$attachment->setId(1);
		$localMessage->setAttachments([$attachment]);
		$message = new Message();
		$transport = $this->createMock(Horde_Mail_Transport::class);
		$attachmentMessage = new DbMessage();
		$attachmentMessage->setMailboxId(1234);
		$attachmentMessage->setUid(11);
		$mailbox = new DbMailbox();
		$mailbox->setAccountId(22);
		$mailbox->setName('mock');

		$account->expects($this->once())
			->method('newMessage')
			->willReturn($message);
		$this->smtpClientFactory->expects($this->once())
			->method('create')
			->with($account)
			->willReturn($transport);
		$this->transmissionService->expects(self::once())
			->method('getAttachments')
			->with($localMessage)
			->willReturn([[
				'type' => 'local',
				'id' => 1,
			]]
			);
		$this->transmissionService->expects(self::once())
			->method('handleAttachment');

		$this->transmission->sendMessage($account, $localMessage);
		$this->assertEquals(LocalMessage::STATUS_RAW, $localMessage->getStatus());
	}

	public function testReplyToAnExistingMessage() {
		$mailAccount = new MailAccount();
		$mailAccount->setUserId('testuser');
		$mailAccount->setSentMailboxId(123);
		/** @var Account|MockObject $account */
		$account = $this->createMock(Account::class);
		$account->method('getMailAccount')->willReturn($mailAccount);
		$account->method('getName')->willReturn('Test User');
		$account->method('getEMailAddress')->willReturn('test@user');
		$localMessage = new LocalMessage();
		$localMessage->setSubject('Test');
		$localMessage->setBody('Test');
		$localMessage->setHtml(false);
		$localMessage->setInReplyToMessageId('321');
		$repliedMessageUid = 321;
		$messageInReply = new DbMessage();
		$messageInReply->setUid($repliedMessageUid);
		$messageInReply->setMessageId('message@server');
		$message = new Message();
		$transport = $this->createMock(Horde_Mail_Transport::class);

		$account->expects($this->once())
			->method('newMessage')
			->willReturn($message);
		$this->smtpClientFactory->expects($this->once())
			->method('create')
			->with($account)
			->willReturn($transport);

		$this->transmission->sendMessage($account, $localMessage);
		$this->assertEquals(LocalMessage::STATUS_RAW, $localMessage->getStatus());
	}

	public function testSaveDraft() {
		$mailAccount = new MailAccount();
		$mailAccount->setUserId('testuser');
		$mailAccount->setDraftsMailboxId(123);
		/** @var Account|MockObject $account */
		$account = $this->createMock(Account::class);
		$account->method('getMailAccount')->willReturn($mailAccount);
		$account->method('getName')->willReturn('Test User');
		$account->method('getEMailAddress')->willReturn('test@user');
		$messageData = NewMessageData::fromRequest($account, 'to@d.com', '', '', 'sub', 'bod');
		$message = new Message();

		$account->expects($this->once())
			->method('newMessage')
			->willReturn($message);
		$client = $this->createMock(Horde_Imap_Client_Socket::class);
		$this->imapClientFactory->expects($this->once())
			->method('getClient')
			->with($account)
			->willReturn($client);
		$draftsMailbox = new DbMailbox();
		$this->mailboxMapper->expects($this->once())
			->method('findById')
			->with(123)
			->willReturn($draftsMailbox);
		$this->messageMapper->expects($this->once())
			->method('save')
			->with($client, $draftsMailbox, $this->anything())
			->willReturn(13);

		[, , $newId] = $this->transmission->saveDraft($messageData);

		$this->assertEquals(13, $newId);
	}

	public function testSendLocalDraft(): void {
		$mailAccount = new MailAccount();
		$mailAccount->setId(10);
		$mailAccount->setUserId('gunther');
		$mailAccount->setName('Gunther');
		$mailAccount->setEmail('gunther@stardewvalley-museum.com');
		$mailAccount->setDraftsMailboxId(123);
		$localMessage = new LocalMessage();
		$localMessage->setType(LocalMessage::TYPE_DRAFT);
		$localMessage->setAccountId($mailAccount->getId());
		$localMessage->setAliasId(2);
		$localMessage->setSendAt(123);
		$localMessage->setSubject('subject');
		$localMessage->setBody('message');
		$localMessage->setHtml(true);
		$localMessage->setInReplyToMessageId('abc');
		$localMessage->setAttachments([]);
		$to = Recipient::fromParams([
			'email' => 'emily@stardewvalleypub.com',
			'label' => 'Emily',
			'type' => Recipient::TYPE_TO
		]);
		$localMessage->setRecipients([$to]);
		$replyMessage = new DbMessage();
		$replyMessage->setMessageId('abc');

		$this->messageMapper->expects(self::once())
			->method('save');

		$this->transmission->saveLocalDraft(new Account($mailAccount), $localMessage);
	}

	public function testSendLocalDraftNoDraftsMailbox(): void {
		$mailAccount = new MailAccount();
		$mailAccount->setId(10);
		$mailAccount->setUserId('gunther');
		$mailAccount->setName('Gunther');
		$mailAccount->setEmail('gunther@stardewvalley-museum.com');
		$localMessage = new LocalMessage();
		$localMessage->setType(LocalMessage::TYPE_DRAFT);
		$localMessage->setAccountId($mailAccount->getId());
		$localMessage->setAliasId(2);
		$localMessage->setSendAt(123);
		$localMessage->setSubject('subject');
		$localMessage->setBody('message');
		$localMessage->setHtml(true);
		$localMessage->setInReplyToMessageId('abc');
		$localMessage->setAttachments([]);
		$to = Recipient::fromParams([
			'email' => 'emily@stardewvalleypub.com',
			'label' => 'Emily',
			'type' => Recipient::TYPE_TO
		]);
		$localMessage->setRecipients([$to]);

		$this->transmissionService->expects(self::exactly(3))
			->method('getAddressList');
		$this->transmissionService->expects(self::once())
			->method('getAttachments');
		$this->aliasService->expects(self::never())
			->method('find');

		$this->expectException(ClientException::class);
		$this->transmission->sendMessage(new Account($mailAccount), $localMessage);
		$this->assertEquals(LocalMessage::STATUS_NO_SENT_MAILBOX, $localMessage->getStatus());
	}
}

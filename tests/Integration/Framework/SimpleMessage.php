<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Mail\Tests\Integration\Framework;

class SimpleMessage {
	/** @var string */
	private $from;

	/** @var string */
	private $to;

	/** @var string|null */
	private $cc;

	/** @var string|null */
	private $bcc;

	/** @var string|null */
	private $date;

	/** @var string|null */
	private $subject;

	/** @var string|null */
	private $body;

	/**
	 * @param string $from
	 * @param string $to
	 * @param string|null $cc
	 * @param string|null $bcc
	 * @param string $date
	 * @param string $subject
	 * @param string $body
	 */
	public function __construct(string $from,
		string $to,
		?string $cc,
		?string $bcc,
		?string $date,
		?string $subject,
		?string $body) {
		$this->from = $from;
		$this->to = $to;
		$this->cc = $cc;
		$this->bcc = $bcc;
		$this->date = $date;
		$this->subject = $subject;
		$this->body = $body;
	}

	public function getFrom(): string {
		return $this->from;
	}

	public function getTo(): string {
		return $this->to;
	}

	public function getCc(): ?string {
		return $this->cc;
	}

	public function getBcc(): ?string {
		return $this->bcc;
	}

	public function getDate(): ?string {
		return $this->date;
	}

	public function getSubject(): ?string {
		return $this->subject;
	}

	public function getBody(): ?string {
		return $this->body;
	}
}

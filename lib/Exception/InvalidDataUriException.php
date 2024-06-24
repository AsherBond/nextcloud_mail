<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Exception;

use Exception;

class InvalidDataUriException extends Exception {
	public function __construct() {
		parent::__construct('Invalid data uri');
	}
}

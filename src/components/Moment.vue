<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<span :data-timestamp="timestamp * 1000" :title="title">{{ formatted }}</span>
</template>

<script>
import moment from '@nextcloud/moment'
import { shortRelativeDatetime } from '../util/shortRelativeDatetime.js'

export default {
	name: 'Moment',
	props: {
		timestamp: {
			type: Number,
			required: true,
		},
		format: {
			type: String,
			default: 'LLL',
		},
	},
	computed: {
		title() {
			return moment.unix(this.timestamp).format(this.format)
		},
		formatted() {
			return shortRelativeDatetime(new Date(this.timestamp * 1000))
		},
	},
}
</script>

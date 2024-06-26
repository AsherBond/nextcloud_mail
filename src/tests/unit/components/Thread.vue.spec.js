/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {createLocalVue, shallowMount} from '@vue/test-utils'

import Nextcloud from '../../../mixins/Nextcloud.js'
import Thread from '../../../components/Thread.vue'
import Vuex from 'vuex'

const localVue = createLocalVue()

localVue.use(Vuex)
localVue.mixin(Nextcloud)

describe('Thread', () => {
	let actions
	let getters
	let store

	beforeEach(() => {
		actions = {
			fetchThread: () => {
				return []
			},
		},
		getters = {
			getEnvelope: () => (id) => {
				if (id === 200) {
					return {
						accountId: 100,
						threadRootId: '123-456-789',
						mailboxId: 10,
					}
				}
				if (id === 300) {
					return {
						accountId: 200,
						threadRootId: '456-789-123',
						mailboxId: 20,
					}
				}
				if (id === 301) {
					return {
						accountId: 200,
						threadRootId: '456-789-123',
						mailboxId: 22,
					}
				}
				if (id === 302) {
					return {
						accountId: 200,
						threadRootId: '456-789-123',
						mailboxId: 23,
					}
				}
				return undefined
			},
			getEnvelopesByThreadRootId: () => (accountId, threadRootId) => {
				if (threadRootId === '123-456-789') {
					return [
						{
							accountId: 100,
							threadRootId: '123-456-789',
							mailboxId: 10,
							databaseId: 1001,
							from: [],
							to: [],
							cc: [],
						},
						{
							accountId: 100,
							threadRootId: '123-456-789',
							mailboxId: 11,
							databaseId: 1002,
							from: [],
							to: [],
							cc: [],
						},
						{
							accountId: 100,
							threadRootId: '123-456-789',
							mailboxId: 10,
							databaseId: 1003,
							from: [],
							to: [],
							cc: [],
						},
					]
				}
				if (threadRootId === '456-789-123') {
					return [
						{
							accountId: 200,
							threadRootId: '456-789-123',
							mailboxId: 20,
							databaseId: 2001,
							from: [],
							to: [],
							cc: [],
						},
						{
							accountId: 200,
							threadRootId: '456-789-123',
							mailboxId: 21,
							databaseId: 2002,
							from: [],
							to: [],
							cc: [],
						},
						{
							accountId: 200,
							threadRootId: '456-789-123',
							mailboxId: 20,
							databaseId: 2003,
							from: [],
							to: [],
							cc: [],
						},
						{
							accountId: 200,
							threadRootId: '456-789-123',
							mailboxId: 22,
							databaseId: 2004,
							from: [],
							to: [],
							cc: [],
						},
						{
							accountId: 200,
							threadRootId: '456-789-123',
							mailboxId: 23,
							databaseId: 2005,
							from: [],
							to: [],
							cc: [],
						},
					]
				}
				return []

			},
			getMailbox: () => (id) => {
				if (id === 10) {
					return {
						databaseId: 10,
						name: 'INBOX',
						accountId: 100,
						specialRole: 'inbox',
					}
				}
				if (id === 20) {
					return {
						databaseId: 20,
						name: 'INBOX',
						accountId: 200,
						specialRole: 'inbox',
					}
				}
				if (id === 22) {
					return {
						databaseId: 22,
						name: 'Trash',
						accountId: 200,
						specialRole: 'trash',
					}
				}
				if (id === 23) {
					return {
						databaseId: 23,
						name: 'Junk',
						accountId: 200,
						specialRole: 'junk',
					}
				}
				return undefined
			},
			getMailboxes: () => (accountId) => {
				if (accountId === 100) {
					return [
						{
							databaseId: 10,
							name: 'INBOX',
							specialRole: 'inbox',
						},
						{
							databaseId: 11,
							name: 'Test',
							specialRole: '',
						},
					]
				}
				if (accountId === 200) {
					return [
						{
							databaseId: 20,
							name: 'INBOX',
							specialRole: 'inbox',
						},
						{
							databaseId: 21,
							name: 'Test',
							specialRole: '',
						},
						{
							databaseId: 22,
							name: 'Trash',
							specialRole: 'trash',
						},
						{
							databaseId: 23,
							name: 'Junk',
							specialRole: 'junk',
						},
					]
				}
				return []
			},
		}
		store = new Vuex.Store({
			actions,
			getters,
		})
	})

	it('empty list when envelope not found', () => {
		const view = shallowMount(Thread, {
			mocks: {
				$route: {
					params: {
						threadId: 100,
					},
				},
			},
			store,
			localVue,
		})

		expect(view.vm.thread).toHaveLength(0)
	})

	it('show messages for thread root from inbox and test folder', () => {
		const view = shallowMount(Thread, {
			mocks: {
				$route: {
					params: {
						threadId: 200,
					},
				},
			},
			store,
			localVue,
		})

		expect(view.vm.thread).toHaveLength(3)
	})

	it('show messages for thread root from inbox and test folder, ignore trash', () => {
		const view = shallowMount(Thread, {
			mocks: {
				$route: {
					params: {
						threadId: 300,
					},
				},
			},
			store,
			localVue,
		})

		expect(view.vm.thread).toHaveLength(3)
	})

	it('show messages for thread root only from trash', () => {
		const view = shallowMount(Thread, {
			mocks: {
				$route: {
					params: {
						threadId: 301,
					},
				},
			},
			store,
			localVue,
		})

		const envelopes = view.vm.thread;
		expect(envelopes).toHaveLength(1)
		expect(envelopes[0].mailboxId).toBe(22)
	})

	it('show messages for thread root only from junk', () => {
		const view = shallowMount(Thread, {
			mocks: {
				$route: {
					params: {
						threadId: 302,
					},
				},
			},
			store,
			localVue,
		})

		const envelopes = view.vm.thread;
		expect(envelopes).toHaveLength(1)
		expect(envelopes[0].mailboxId).toBe(23)
	})
})

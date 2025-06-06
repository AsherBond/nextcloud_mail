/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import logger from '../../../logger.js'
import dragEventBus from '../util/dragEventBus.js'

export class DroppableMailbox {

	constructor(el, componentInstance, options) {
		this.el = el
		this.options = options
		this.mainStore = options.mainStore
		this.registerListeners.bind(this)(el)
		this.setInitialAttributes()
	}

	setInitialAttributes() {
		this.draggableInfo = {}
		this.setStatus('enabled')
	}

	update(el, instance) {
		this.setInitialAttributes()
		this.options = instance.options
	}

	registerListeners(el) {
		dragEventBus.on('drag-start', this.onDragStart.bind(this))
		dragEventBus.on('drag-end', this.onDragEnd.bind(this))

		// event listeners need to be attached to the first child element
		// (a button or an anchor tag) instead of the root el, because there
		// can be sub-mailboxes within the root element of the directive
		el.firstChild.addEventListener('dragover', this.onDragOver.bind(this))
		el.firstChild.addEventListener('dragleave', this.onDragLeave.bind(this))
		el.firstChild.addEventListener('drop', this.onDrop.bind(this))
	}

	removeListeners(el) {
		dragEventBus.off('drag-start', this.onDragStart)
		dragEventBus.off('drag-end', this.onDragEnd)

		el.firstChild.removeEventListener('dragover', this.onDragOver)
		el.firstChild.removeEventListener('dragleave', this.onDragLeave)
		el.firstChild.removeEventListener('drop', this.onDrop)
	}

	setStatus(status) {
		this.el.setAttribute('droppable-mailbox', status)
	}

	onDragStart(draggableInfo) {
		this.draggableInfo = draggableInfo

		if (!this.canBeDropped()) {
			this.setStatus('disabled')
		}
	}

	canBeDropped() {
		return this.isSameAccount() && this.options.isValidDropTarget
	}

	isSameAccount() {
		return this.draggableInfo.accountId === this.options.accountId
	}

	/**
	 * Is the user currently dragging a valid object?
	 *
	 * @return {boolean}
	 */
	get isCurrentlyDragging() {
		return Object.keys(this.draggableInfo).length > 0
	}

	onDragEnd() {
		this.setInitialAttributes()
	}

	onDragOver(event) {
		if (!this.isCurrentlyDragging) {
			return
		}

		event.preventDefault()

		// Prevent dropping into current folder
		if (this.draggableInfo.mailboxId === this.options.mailboxId) {
			return
		}

		if (this.options.isValidDropTarget) {
			this.setStatus('dragover')
		}

		event.dataTransfer.dropEffect = 'move'
	}

	onDragLeave(event) {
		if (!this.isCurrentlyDragging) {
			return
		}

		event.preventDefault()
		this.setStatus('enabled')
	}

	async onDrop(event) {
		if (!this.isCurrentlyDragging) {
			return
		}

		event.preventDefault()

		// Prevent dropping into current folder
		if (this.draggableInfo.mailboxId === this.options.mailboxId) {
			return
		}

		this.setInitialAttributes()
		const envelopesBeingDragged = JSON.parse(event.dataTransfer.getData('text'))
		dragEventBus.emit('envelopes-dropped', { envelopes: envelopesBeingDragged })

		try {
			const processedEnvelopes = envelopesBeingDragged.map(async envelope => {
				const processed = await this.processDroppedItem(envelope)
				return processed
			})
			await Promise.all(processedEnvelopes)
		} catch (error) {
			logger.error('could not process dropped messages', error)
		} finally {
			dragEventBus.emit('envelopes-moved', {
				mailboxId: this.options.mailboxId,
				movedEnvelopes: envelopesBeingDragged,
			})
		}
	}

	async processDroppedItem(envelope) {
		const item = document.querySelector(`[data-envelope-id="${envelope.databaseId}"]`)
		item.setAttribute('draggable-envelope', 'pending')

		try {
			if (this.mainStore.getPreference('layout-message-view') === 'threaded') {
				await this.mainStore.moveThread({
					envelope,
					destMailboxId: this.options.mailboxId,
				})
			} else {
				await this.mainStore.moveMessage({
					id: envelope.databaseId,
					destMailboxId: this.options.mailboxId,
				})
			}
		} catch (error) {
			item.removeAttribute('draggable-envelope')
			logger.error('could not move messages', error)
		}
	}

}

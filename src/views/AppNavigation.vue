<!--
Nextcloud - Tasks

@author Raimund Schlüßler
@copyright 2018 Raimund Schlüßler <raimund.schluessler@mailbox.org>

This library is free software; you can redistribute it and/or
modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
License as published by the Free Software Foundation; either
version 3 of the License, or any later version.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU AFFERO GENERAL PUBLIC LICENSE for more details.

You should have received a copy of the GNU Affero General Public
License along with this library. If not, see <http://www.gnu.org/licenses/>.

-->

<template>
	<NcAppNavigation>
		<template #list>
			<NcAppNavigationItem v-for="collection in collections"
				v-show="!hideCollection(collection)"
				:id="'collection_' + collection.id"
				:key="collection.id"
				:collection-id="collection.id"
				:to="{ name: 'collections', params: { collectionId: collection.id } }"
				:name="collection.displayName"
				class="collection reactive"
				draggable="false"
				@dragstart="dragStart"
				@drop="(e) => dropTaskOnCollection(e, collection)"
				@dragover="dragOver"
				@dragenter="(e) => dragEnter(e, collection)"
				@dragleave="dragLeave"
				@click="setInitialRoute(`/collections/${collection.id}`)">
				<template #icon>
					<component :is="collection.icon"
						:size="20" />
				</template>
				<template #counter>
					<NcCounterBubble v-show="collectionCount(collection.id)" :count="collectionCount(collection.id)" />
				</template>
			</NcAppNavigationItem>
			<Sortable class="draggable-container"
				:list="calendars"
				item-key="id"
				:options="{swapThreshold: 0.30, delay: 500, delayOnTouchOnly: true, touchStartThreshold: 3}"
				@dragstart="setData($event)"
				@update="update">
				<template #item="{element}">
					<ListItemCalendar :calendar="element"
						@click="setInitialRoute(`/calendars/${element.id}`)" />
				</template>
			</Sortable>
			<NcAppNavigationItem v-click-outside="() => {creating = false}"
				:name="t('tasks', 'Add List…')"
				:class="{'collection--edit': creating}"
				class="collection reactive"
				@click="startCreate($event)">
				<template #icon>
					<Plus :size="20" />
				</template>
				<li>
					<div class="app-navigation-entry-edit">
						<NcTextField ref="newListInput"
							v-model="newCalendarName"
							type="text"
							:show-trailing-button="newCalendarName !== ''"
							trailing-button-icon="arrowRight"
							:error="nameError"
							:label="t('tasks', 'New list')"
							@trailing-button-click="create()"
							@keyup="checkName($event)">
							<Plus :size="16" />
						</NcTextField>
						<Colorpicker :selected-color="selectedColor" @color-selected="setColor" />
					</div>
				</li>
			</NcAppNavigationItem>
			<Trashbin v-if="hasTrashBin" />
		</template>
		<template #footer>
			<AppNavigationSettings />
		</template>
	</NcAppNavigation>
</template>

<script>
import ListItemCalendar from '../components/AppNavigation/ListItemCalendar.vue'
import Colorpicker from '../components/AppNavigation/Colorpicker.vue'
import AppNavigationSettings from '../components/AppNavigation/AppNavigationSettings.vue'
import Trashbin from '../components/AppNavigation/Trashbin.vue'

import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import CalendarToday from 'vue-material-design-icons/CalendarToday.vue'
import CalendarWeek from 'vue-material-design-icons/CalendarWeek.vue'
import Check from 'vue-material-design-icons/Check.vue'
import CircleOutline from 'vue-material-design-icons/CircleOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Star from 'vue-material-design-icons/Star.vue'
import TrendingUp from 'vue-material-design-icons/TrendingUp.vue'

import { Sortable } from 'sortablejs-vue3'
import { vOnClickOutside as ClickOutside } from '@vueuse/components'
import { mapState, mapGetters, mapActions } from 'vuex'

export default {
	components: {
		ListItemCalendar,
		Colorpicker,
		Trashbin,
		NcAppNavigation,
		NcAppNavigationItem,
		NcCounterBubble,
		NcTextField,
		AppNavigationSettings,
		Sortable,
		CalendarToday,
		CalendarWeek,
		Check,
		CircleOutline,
		Plus,
		TrendingUp,
		Star,
	},
	directives: {
		ClickOutside,
	},
	inject: ['$OCA'],
	data() {
		return {
			editing: '',
			shareOpen: '',
			copySuccess: false,
			copied: false,
			creating: false,
			nameError: false,
			newCalendarName: '',
			selectedColor: '',
		}
	},
	computed: {
		...mapState({
			collections: state => state.collections.collections,
		}),
		...mapGetters({
			calendars: 'getSortedCalendars',
			collectionCount: 'getCollectionCount',
			calendarCount: 'getCalendarCount',
			isCalendarNameUsed: 'isCalendarNameUsed',
			getTask: 'getTaskByUri',
			initialRoute: 'initialRoute',
			hasTrashBin: 'hasTrashBin',
		}),
	},
	methods: {
		t,

		...mapActions([
			'appendCalendar',
			'setPriority',
			'setPercentComplete',
			'setDate',
			'setSetting',
			'setCalendarOrder',
		]),

		/**
		 * Indicate that we drag a calendar item
		 *
		 * @param {object} e The event object
		 */
		setData(e) {
			e.dataTransfer.setData('text/calendar', 'calendar')
		},

		/**
		 * Called when the calendar list order is changed.
		 *
		 * @param {object} $event The event which caused the sorting
		 */
		update($event) {
			const newIndex = $event.newIndex
			const oldIndex = $event.oldIndex

			// If the calendars array has no entry, we don't need to sort.
			if (this.calendars.length === 0) {
				return
			}
			// If the calendar is inserted at its current position, don't sort.
			if (newIndex === oldIndex) {
				return
			}

			// Get a copy of the sorted calendars array
			const calendars = [...this.calendars]

			// In case the task to move is already in the array, move it to the new position
			if (oldIndex > -1) {
				calendars.splice(newIndex, 0, calendars.splice(oldIndex, 1)[0])
			} else {
				return
			}

			// Get the new sort order for the moved calendar and apply it.
			// We just do that to minimize the number of other calendars to be changed.
			let newSortOrder
			if (newIndex + 1 < calendars.length
				&& (newIndex < 1 || calendars[newIndex + 1].order - 1 > calendars[newIndex - 1].order)) {
				newSortOrder = calendars[newIndex + 1].order - 1
			} else {
				newSortOrder = calendars[newIndex - 1].order + 1
			}
			if (newSortOrder < 0) {
				newSortOrder = 0
			}
			// Apply the new sort order for the moved calendar
			this.setCalendarOrder({ calendar: calendars[newIndex], order: newSortOrder })

			// Check the sort orders to be strictly monotonous
			let currentIndex = 1
			while (currentIndex < calendars.length) {
				if (calendars[currentIndex].order <= calendars[currentIndex - 1].order) {
					const order = { calendar: calendars[currentIndex], order: calendars[currentIndex - 1].order + 1 }
					this.setCalendarOrder(order)
				}
				currentIndex++
			}
		},

		/**
		 * Handle the drag start
		 *
		 * @param {object} e The event object
		 * @return {boolean}
		 */
		dragStart(e) {
			e.stopPropagation()
			e.preventDefault()
			return false
		},
		/**
		 * Handle the drag over
		 *
		 * @param {object} e The event object
		 * @return {boolean}
		 */
		dragOver(e) {
			if (e.preventDefault) {
				e.preventDefault()
			}
			return false
		},
		/**
		 * Set the appropriate class on hovering
		 *
		 * @param {object} e The event object
		 * @param {object} collection The collection on which the task was dropped
		 */
		dragEnter(e, collection) {
			// Check if dropping here is allowed
			if (!['starred', 'completed', 'today', 'week'].includes(collection.id)) {
				return
			}
			// Dragging calendars has no effect
			if (e.dataTransfer.getData('text/calendar') === 'calendar') {
				return
			}
			// Get the correct element, in case we hover a child.
			if (e.target.closest) {
				const target = e.target.closest('li.collection')
				if (target) {
					const collections = document.querySelectorAll('li.collection')
					collections.forEach((f) => { f.classList.remove('dnd-hover') })
					target.classList.add('dnd-hover')
				}
			}
		},
		/**
		 * Remove the hovering class after leaving
		 *
		 * @param {object} e The event object
		 */
		dragLeave(e) {
			// Don't do anything if we leave towards a child element.
			if (e.target.contains(e.relatedTarget)) {
				return
			}
			// Get the correct element, in case we leave directly from a child.
			if (e.target.closest) {
				const target = e.target.closest('li.collection')
				if (!target || target.contains(e.relatedTarget)) {
					return
				}
				target.classList.remove('dnd-hover')
			}
		},
		/**
		 * Drop a task on a collection
		 *
		 * @param {object} e The event object
		 * @param {object} collection The collection
		 */
		dropTaskOnCollection(e, collection) {
			// Remove all hover classes
			const collections = document.querySelectorAll('li.collection')
			collections.forEach((f) => { f.classList.remove('dnd-hover') })
			// Check if dropping here is allowed
			if (!['starred', 'completed', 'today', 'week'].includes(collection.id)) {
				return
			}
			const taskUri = e.dataTransfer.getData('text/uri')
			if (taskUri) {
				const task = this.getTask(taskUri)
				switch (collection.id) {
				case 'starred':
					this.setPriority({ task, priority: 1 })
					break
				case 'completed':
					this.setPercentComplete({ task, complete: 100 })
					break
				case 'today':
					this.setDate({ task, day: 0 })
					break
				case 'week':
					this.setDate({ task, day: 6 })
					break
				}
			}
		},
		hideCollection(collection) {
			switch (collection.show) {
			case 0:
				return true
			case 1:
				return false
			case 2:
				return this.collectionCount(collection.id) < 1
			}
		},
		startCreate(e) {
			if (this.$OCA.Theming) {
				this.selectedColor = this.$OCA.Theming.color
			} else {
				this.selectedColor = '#0082C9'
			}
			this.newCalendarName = ''
			this.creating = true
			this.$nextTick(
				() => this.$refs.newListInput.$refs.inputField.$refs.input.focus(),
			)
			e.stopPropagation()
		},
		async create() {
			if (!this.isNameAllowed(this.newCalendarName).allowed) {
				return
			}
			try {
				const { id: calendarId } = await this.appendCalendar({ displayName: this.newCalendarName, color: this.selectedColor })
				if (calendarId) {
					await this.$router.push({ name: 'calendars', params: { calendarId } })
				}
			} catch (error) {
				console.debug(error)
				showError(t('tasks', 'An error occurred, unable to create the list.'))
			} finally {
				this.creating = false
			}
		},
		checkName(event) {
			const check = this.isNameAllowed(this.newCalendarName)
			if (!check.allowed) {
				this.nameError = true
			} else {
				this.nameError = false
			}
			if (event.keyCode === 13) {
				this.create()
			}
			if (event.keyCode === 27) {
				event.preventDefault()
				this.creating = false
				this.editing = false
				this.nameError = false
			}
		},
		isNameAllowed(name) {
			const check = {
				allowed: false,
				msg: '',
			}
			if (this.isCalendarNameUsed(name)) {
				check.msg = t('tasks', 'The name "{calendar}" is already used.', { calendar: name }, undefined, { sanitize: false, escape: false })
			} else if (!name) {
				check.msg = t('tasks', 'An empty name is not allowed.')
			} else {
				check.allowed = true
			}
			return check
		},
		setColor(color) {
			this.selectedColor = color
		},
		/**
		 * Saves the current route as new initial route
		 *
		 * @param {string} route The new initial route
		 */
		setInitialRoute(route) {
			if (route === this.initialRoute) {
				return
			}
			this.setSetting({ type: 'initialRoute', value: route })
		},
	},
}
</script>

<style lang="scss" scoped>
$color-error: #e9322d;

.app-navigation li {
	&.dnd-hover,
	&.sortable-ghost {
		background-color: var(--color-primary-element-light);
		box-shadow: 0 0 3px var(--color-primary-element);
		z-index: 1000;
	}
}

:deep(.collection) {
	&.collection--edit {
		.app-navigation-entry {
			display: none;
		}

		.app-navigation-entry-edit {
			display: inline-block;
			width: 100%;
		}
	}

	.app-navigation-entry-edit {
		padding-left: 5px !important;
		display: none;
		position: relative;
	}
}
</style>

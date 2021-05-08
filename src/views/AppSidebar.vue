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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU AFFERO GENERAL PUBLIC LICENSE for more details.

You should have received a copy of the GNU Affero General Public
License along with this library.  If not, see <http://www.gnu.org/licenses/>.

-->

<template>
	<AppSidebar
		:title="title"
		:title-editable="editingTitle"
		:linkify-title="true"
		:subtitle="subtitle"
		:empty="!task"
		@start-editing="newTitle = task.summary"
		@update:titleEditable="editTitle"
		@update:title="updateTitle"
		@submit-title="saveTitle"
		@close="closeAppSidebar()">
		<template v-if="task" #description>
			<DatetimePickerItem
				v-show="!readOnly || task.start"
				:date="task.startMoment"
				:value="newStartDate"
				:all-day="allDay"
				:property-string="startDateString"
				:read-only="readOnly"
				:task="task"
				@editing="(editing) => editingStart = editing"
				@setValue="setStartDate">
				<CalendarStart slot="icon" :size="24" decorative />
			</DatetimePickerItem>
			<DatetimePickerItem
				v-show="!readOnly || task.due"
				:date="task.dueMoment"
				:value="newDueDate"
				:all-day="allDay"
				:property-string="dueDateString"
				:read-only="readOnly"
				:task="task"
				@editing="(editing) => editingDue = editing"
				@setValue="setDueDate">
				<CalendarEnd slot="icon" :size="24" decorative />
			</DatetimePickerItem>
			<CheckboxItem
				v-show="showAllDayToggle"
				id="allDayToggle"
				:checked="allDay"
				:read-only="readOnly"
				:property-string="$t('tasks', 'All day')"
				@setChecked="toggleAllDay(task)" />
			<CalendarPickerItem
				:disabled="readOnly"
				:calendar="task.calendar"
				:calendars="targetCalendars"
				@changeCalendar="changeCalendar" />
		</template>

		<template #secondary-actions>
			<ActionButton v-if="!readOnly"
				@click="togglePinned(task)">
				<PinOff v-if="task.pinned"
					slot="icon"
					:size="24"
					decorative />
				<Pin v-else
					slot="icon"
					:size="24"
					decorative />
				{{ task.pinned ? $t('tasks', 'Unpin') : $t('tasks', 'Pin') }}
			</ActionButton>
			<ActionLink v-if="showInCalendar"
				:href="calendarLink"
				:close-after-click="true"
				target="_blank">
				<Calendar slot="icon" :size="24" decorative />
				{{ $t('tasks', 'Show in Calendar') }}
			</ActionLink>
			<ActionButton v-if="!readOnly"
				:close-after-click="true"
				@click="editTitle(true)">
				<Pencil slot="icon" :size="24" decorative />
				{{ $t('tasks', 'Edit title') }}
			</ActionButton>
			<ActionLink
				:href="downloadURL"
				:close-after-click="true">
				<Download slot="icon" :size="24" decorative />
				{{ $t('tasks', 'Download') }}
			</ActionLink>
			<ActionButton v-if="!readOnly"
				@click="removeTask">
				<Delete slot="icon" :size="24" decorative />
				{{ $t('tasks', 'Delete') }}
			</ActionButton>
		</template>

		<template #tertiary-actions>
			<span class="app-sidebar-header__checkbox">
				<input :id="'detailsToggleCompleted_' + task.uid"
					type="checkbox"
					class="checkbox"
					:class="{'disabled': readOnly}"
					:checked="task.completed"
					:aria-checked="task.completed"
					:disabled="readOnly"
					:aria-label="$t('tasks', 'Task is completed')"
					@click="toggleCompleted(task)">
				<label :class="[priorityClass]" :for="'detailsToggleCompleted_' + task.uid" />
			</span>
		</template>

		<AppSidebarTab v-if="task"
			id="app-sidebar-tab-details"
			class="app-sidebar-tab"
			icon="icon-details"
			:name="$t('tasks', 'Details')"
			:order="0">
			<div>
				<MultiselectItem
					v-show="!readOnly || task.class !== 'PUBLIC'"
					:value="classSelect.find( _ => _.type === task.class )"
					:options="classSelect"
					:disabled="readOnly || task.calendar.isSharedWithMe"
					:placeholder="$t('tasks', 'Select a classification')"
					icon="IconEye"
					@changeValue="changeClass" />
				<MultiselectItem
					v-show="!readOnly || task.status"
					:value="statusOptions.find( _ => _.type === task.status )"
					:options="statusOptions"
					:disabled="readOnly"
					:placeholder="$t('tasks', 'Select a status')"
					icon="IconPulse"
					@changeValue="changeStatus" />
				<SliderItem
					v-show="!readOnly || task.priority"
					:value="task.priority"
					:property-string="priorityString"
					:read-only="readOnly"
					:min-value="0"
					:max-value="9"
					:color="priorityColor"
					:task="task"
					@setValue="({task, value}) => setPriority({ task, priority: value })">
					<Star slot="icon" :size="24" decorative />
				</SliderItem>
				<SliderItem
					v-show="!readOnly || task.complete"
					:value="task.complete"
					:property-string="completeString"
					:read-only="readOnly"
					:min-value="0"
					:max-value="100"
					:color="task.complete > 0 ? '#4271a6' : null"
					:task="task"
					@setValue="({task, value}) => setPercentComplete({ task, complete: value })">
					<Percent slot="icon" :size="24" decorative />
				</SliderItem>
				<TagsItem
					v-show="!readOnly || task.tags.length > 0"
					:options="tags"
					:tags="task.tags"
					:disabled="readOnly"
					:placeholder="$t('tasks', 'Select tags')"
					icon="IconTag"
					@addTag="updateTag"
					@setTags="updateTags" />
			</div>
		</AppSidebarTab>
		<EmptyContent v-else
			:icon="taskStatusIcon">
			{{ taskStatusLabel }}
		</EmptyContent>
		<AppSidebarTab v-if="task && (!readOnly || task.note)"
			id="app-sidebar-tab-notes"
			class="app-sidebar-tab"
			icon="icon-note"
			:name="$t('tasks', 'Notes')"
			:order="1">
			<NotesItem
				v-show="!readOnly || task.note"
				:value="task.note"
				:read-only="readOnly"
				:task="task"
				@setValue="({task, value}) => setNote({ task, note: value })" />
		</AppSidebarTab>
		<!-- <AppSidebarTab v-if="task"
			id="app-sidebar-tab-reminder"
			class="app-sidebar-tab"
			icon="icon-reminder"
			:name="$t('tasks', 'Reminders')"
			:order="2">
			Reminders
		</AppSidebarTab>
		<AppSidebarTab v-if="task"
			id="app-sidebar-tab-repeat"
			class="app-sidebar-tab"
			icon="icon-repeat"
			:name="$t('tasks', 'Repeat')"
			:order="3">
			Repeat
		</AppSidebarTab> -->
	</AppSidebar>
</template>

<script>
import CheckboxItem from '../components/AppSidebar/CheckboxItem.vue'
import DatetimePickerItem from '../components/AppSidebar/DatetimePickerItem.vue'
import CalendarPickerItem from '../components/AppSidebar/CalendarPickerItem.vue'
import MultiselectItem from '../components/AppSidebar/MultiselectItem.vue'
import SliderItem from '../components/AppSidebar/SliderItem.vue'
import TagsItem from '../components/AppSidebar/TagsItem.vue'
import NotesItem from '../components/AppSidebar/NotesItem.vue'
// import TaskStatusDisplay from '../components/TaskStatusDisplay.vue'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import { generateUrl } from '@nextcloud/router'

import Calendar from 'vue-material-design-icons/Calendar.vue'
import CalendarStart from 'vue-material-design-icons/CalendarStart.vue'
import CalendarEnd from 'vue-material-design-icons/CalendarEnd.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Download from 'vue-material-design-icons/Download.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Pin from 'vue-material-design-icons/Pin.vue'
import PinOff from 'vue-material-design-icons/PinOff.vue'
import Star from 'vue-material-design-icons/Star.vue'
import Percent from 'vue-material-design-icons/Percent.vue'

import { mapGetters, mapActions } from 'vuex'

export default {
	components: {
		AppSidebar,
		AppSidebarTab,
		ActionButton,
		ActionLink,
		CheckboxItem,
		DatetimePickerItem,
		Calendar,
		CalendarStart,
		CalendarEnd,
		Delete,
		Download,
		Pencil,
		Pin,
		PinOff,
		Star,
		Percent,
		EmptyContent,
		MultiselectItem,
		SliderItem,
		TagsItem,
		CalendarPickerItem,
		NotesItem,
		// TaskStatusDisplay,
	},
	data() {
		return {
			editingTitle: false,
			editingStart: false,
			editingDue: false,
			loading: false,
			classSelect: [
				{
					displayName: this.$t('tasks', 'When shared show full event'),
					type: 'PUBLIC',
					icon: 'IconEye',
				},
				{
					displayName: this.$t('tasks', 'When shared show only busy'),
					type: 'CONFIDENTIAL',
					icon: 'IconCalendarRemove',
					optionClass: 'active',
				},
				{
					displayName: this.$t('tasks', 'When shared hide this event'),
					type: 'PRIVATE',
					icon: 'IconEyeOff',
					optionClass: 'active',
				},
			],
			newTitle: '',
		}
	},
	computed: {
		title() {
			return this.task ? this.task.summary : ''
		},
		subtitle() {
			if (!this.task) {
				return ''
			}
			if (this.task?.completed && this.task.completedDateMoment.isValid()) {
				return this.task.completedDateMoment.calendar(null, {
					lastDay: this.$t('tasks', '[Completed yesterday at] LT'),
					sameDay: this.$t('tasks', '[Completed today at] LT'),
					nextDay: this.$t('tasks', '[Completed] L'),
					lastWeek: this.$t('tasks', '[Completed last] dddd [at] LT'),
					nextWeek: this.$t('tasks', '[Completed] dddd [at] LT'),
					sameElse: this.$t('tasks', '[Completed] L'),
				})
			}
			if (this.task?.modifiedMoment.isValid()) {
				return this.task.modifiedMoment.calendar(null, {
					lastDay: this.$t('tasks', '[Last modified yesterday at] LT'),
					sameDay: this.$t('tasks', '[Last modified today at] LT'),
					nextDay: this.$t('tasks', '[Last modified] L'),
					lastWeek: this.$t('tasks', '[Last modified last] dddd [at] LT'),
					nextWeek: this.$t('tasks', '[Last modified] dddd [at] LT'),
					sameElse: this.$t('tasks', '[Last modified] L'),
				})
			}
			if (this.task?.createdMoment.isValid()) {
				return this.task.createdMoment.calendar(null, {
					lastDay: this.$t('tasks', '[Created yesterday at] LT'),
					sameDay: this.$t('tasks', '[Created today at] LT'),
					nextDay: this.$t('tasks', '[Created] L'),
					lastWeek: this.$t('tasks', '[Created last] dddd [at] LT'),
					nextWeek: this.$t('tasks', '[Created] dddd [at] LT'),
					sameElse: this.$t('tasks', '[Created] L'),
				})
			}
			return ''
		},
		statusOptions() {
			const statusOptions = [
				{
					displayName: this.$t('tasks', 'Needs action'),
					type: 'NEEDS-ACTION',
					icon: 'IconAlertBoxOutline',
					optionClass: 'active',
				},
				{
					displayName: this.$t('tasks', 'Completed'),
					type: 'COMPLETED',
					icon: 'IconCheck',
					optionClass: 'active',
				},
				{
					displayName: this.$t('tasks', 'In process'),
					type: 'IN-PROCESS',
					icon: 'IconTrendingUp',
					optionClass: 'active',
				},
				{
					displayName: this.$t('tasks', 'Canceled'),
					type: 'CANCELLED',
					icon: 'IconCancel',
					optionClass: 'active',
				},
			]
			if (this.task.status) {
				return statusOptions.concat([{
					displayName: this.$t('tasks', 'Clear status'),
					type: null,
					icon: 'IconDelete',
					optionClass: 'center',
				}])
			}
			return statusOptions
		},
		/**
		 * Returns the download url as a string or null if event is loading or does not exist on the server (yet)
		 *
		 * @returns {string|null}
		 */
		downloadURL() {
			if (!this.task) {
				return null
			}
			return this.task?.url + '?export'
		},
		/**
		 * Initializes the start date of a task
		 *
		 * @returns {Date} The start date moment
		 */
		newStartDate() {
			const start = this.task.startMoment
			if (start.isValid()) {
				return start.toDate()
			}
			const due = this.task.dueMoment
			let reference = moment().add(1, 'h')
			if (due.isBefore(reference)) {
				reference = due.subtract(1, 'm')
			}
			reference.startOf(this.allDay ? 'day' : 'hour')
			return reference.toDate()
		},

		/**
		 * Initializes the due date of a task
		 *
		 * @returns {Date} The due date moment
		 */
		newDueDate() {
			const due = this.task.dueMoment
			if (due.isValid()) {
				return due.toDate()
			}
			const start = this.task.startMoment
			const reference = start.isAfter() ? start : moment()
			if (this.allDay) {
				reference.startOf('day').add(1, 'd')
			} else {
				reference.startOf('hour').add(1, 'h')
			}
			return reference.toDate()
		},
		taskStatusLabel() {
			return this.loading ? this.$t('tasks', 'Loading task from server.') : this.$t('tasks', 'Task not found!')
		},
		taskStatusIcon() {
			return this.loading ? 'icon-loading' : 'icon-search'
		},
		/**
		 * Whether we treat the task as read-only.
		 * We also treat tasks in shared calendars with an access class other than 'PUBLIC'
		 * as read-only.
		 *
		 * @returns {Boolean} Is the task read-only
		 */
		readOnly() {
			return this.task.calendar.readOnly || (this.task.calendar.isSharedWithMe && this.task.class !== 'PUBLIC')
		},
		/**
		 * Whether the dates of a task are all-day
		 * When no dates are set, we consider the last used value.
		 *
		 * @returns {Boolean} Are the dates all-day
		 */
		allDay() {
			if (this.task.startMoment.isValid() || this.task.dueMoment.isValid()) {
				return !!this.task.allDay
			} else {
				return !!this.$store.state.settings.settings.allDay
			}
		},
		showInCalendar() {
			// Only tasks with a due date show up in the calendar
			return !!this.showTaskInCalendar && this.task.dueMoment.isValid()
		},
		calendarLink() {
			return generateUrl(`apps/calendar/${this.calendarView}/${this.task.dueMoment.format('YYYY-MM-DD')}`)
		},
		startDateString() {
			const $t = this.$t
			if (this.task.startMoment.isValid()) {
				if (this.allDay) {
					return this.task.startMoment.calendar(null, {
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. Please translate the string and keep the brackets.
						sameDay: this.$t('tasks', '[Starts today]'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. Please translate the string and keep the brackets.
						nextDay: this.$t('tasks', '[Starts tomorrow]'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986'. Please translate the string, and keep the brackets and the "LL".
						nextWeek: this.$t('tasks', '[Starts on] LL'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. Please translate the string and keep the brackets.
						lastDay: this.$t('tasks', '[Started yesterday]'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986'. Please translate the string, and keep the brackets and the "LL".
						lastWeek: this.$t('tasks', '[Started on] LL'),
						sameElse(now) {
							if (this.isBefore(now)) {
								// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986'. Please translate the string, and keep the brackets and the "LL".
								return $t('tasks', '[Started on] LL')
							} else {
								// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986'. Please translate the string, and keep the brackets and the "LL".
								return $t('tasks', '[Starts on] LL')
							}
						},
					})
				} else {
					return this.task.startMoment.calendar(null, {
						sameDay(now) {
							if (this.isBefore(now)) {
								// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets and the "LT".
								return $t('tasks', '[Started today at] LT')
							} else {
								// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets and the "LT".
								return $t('tasks', '[Starts today at] LT')
							}
						},
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets and the "LT".
						nextDay: this.$t('tasks', '[Starts tomorrow at] LT'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986' and "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets the "LL" and the "LT".
						nextWeek: this.$t('tasks', '[Starts on] LL [at] LT'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets and the "LT".
						lastDay: this.$t('tasks', '[Started yesterday at] LT'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986' and "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets the "LL" and the "LT".
						lastWeek: this.$t('tasks', '[Started on] LL [at] LT'),
						sameElse(now) {
							if (this.isBefore(now)) {
								// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986' and "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets the "LL" and the "LT".
								return $t('tasks', '[Started on] LL [at] LT')
							} else {
								// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986' and "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets the "LL" and the "LT".
								return $t('tasks', '[Starts on] LL [at] LT')
							}
						},
					})
				}
			} else {
				return this.$t('tasks', 'Set start date')
			}
		},
		dueDateString() {
			const $t = this.$t
			if (this.task.dueMoment.isValid()) {
				if (this.allDay) {
					return this.task.dueMoment.calendar(null, {
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. Please translate the string and keep the brackets.
						sameDay: this.$t('tasks', '[Due today]'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. Please translate the string and keep the brackets.
						nextDay: this.$t('tasks', '[Due tomorrow]'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986'. Please translate the string and keep the brackets and the "LL".
						nextWeek: this.$t('tasks', '[Due on] LL'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. Please translate the string, but keep the brackets.
						lastDay: this.$t('tasks', '[Was due yesterday]'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986'. Please translate the string, but keep the brackets and the "LL".
						lastWeek: this.$t('tasks', '[Was due on] LL'),
						sameElse(now) {
							if (this.isBefore(now)) {
								// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. Please translate the string, but keep the brackets and the "LL".
								return $t('tasks', '[Was due on] LL')
							} else {
								// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. Please translate the string, but keep the brackets and the "LL".
								return $t('tasks', '[Due on] LL')
							}
						},
					})
				} else {
					return this.task.dueMoment.calendar(null, {
						sameDay(now) {
							if (this.isBefore(now)) {
								// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets and the "LT".
								return $t('tasks', '[Was due today at] LT')
							} else {
								// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets and the "LT".
								return $t('tasks', '[Due today at] LT')
							}
						},
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets and the "LT".
						nextDay: this.$t('tasks', '[Due tomorrow at] LT'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets and the "LT".
						nextWeek: this.$t('tasks', '[Due on] LL [at] LT'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets and the "LT".
						lastDay: this.$t('tasks', '[Was due yesterday at] LT'),
						// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986' and "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets the "LL" and the "LT".
						lastWeek: this.$t('tasks', '[Was due on] LL [at] LT'),
						sameElse(now) {
							if (this.isBefore(now)) {
								// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986' and "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets the "LL" and the "LT".
								return $t('tasks', '[Was due on] LL [at] LT')
							} else {
								// TRANSLATORS This is a string for moment.js. The square brackets escape the string from moment.js. "LL" will be replaced with a date, e.g. 'September 4 1986' and "LT" will be replaced with a time, e.g. '08:30 PM'. Please translate the string and keep the brackets the "LL" and the "LT".
								return $t('tasks', '[Due on] LL [at] LT')
							}
						},
					})
				}
			} else {
				return this.$t('tasks', 'Set due date')
			}
		},
		showAllDayToggle() {
			return !this.readOnly && (this.task.due || this.task.start || this.editingStart || this.editingDue)
		},
		priorityColor() {
			if (+this.task.priority > 5) {
				return '#4271a6'
			}
			if (+this.task.priority === 5) {
				return '#fd0'
			}
			if (+this.task.priority > 0) {
				return '#b3312d'
			}
			return null
		},
		priorityString() {
			if (+this.task.priority > 5) {
				return this.$t('tasks', 'Priority {priority}: low', { priority: this.task.priority })
			}
			if (+this.task.priority === 5) {
				return this.$t('tasks', 'Priority {priority}: medium', { priority: this.task.priority })
			}
			if (+this.task.priority > 0) {
				return this.$t('tasks', 'Priority {priority}: high', { priority: this.task.priority })
			}
			return this.$t('tasks', 'No priority assigned')
		},
		priorityClass() {
			if (+this.task.priority > 5) {
				return 'priority--low'
			}
			if (+this.task.priority === 5) {
				return 'priority--medium'
			}
			if (+this.task.priority > 0) {
				return 'priority--high'
			}
			return null
		},
		completeString() {
			return this.$t('tasks', '{percent} % completed', { percent: this.task.complete })
		},
		targetCalendars() {
			let calendars = this.writableCalendars
			// Tasks with an access class other than PUBLIC cannot be moved
			// into calendars shared with me
			if (this.task.class !== 'PUBLIC') {
				calendars = calendars.filter(calendar => !calendar.isSharedWithMe)
			}
			return calendars
		},
		...mapGetters({
			writableCalendars: 'getSortedWritableCalendars',
			task: 'getTaskByRoute',
			calendar: 'getCalendarByRoute',
			calendars: 'getSortedCalendars',
			tags: 'tags',
			showTaskInCalendar: 'showTaskInCalendar',
			calendarView: 'calendarView',
		}),
	},

	watch: {
		$route: 'loadTask',
		calendars: 'loadTask',
	},
	mounted() {
		subscribe('tasks:close-appsidebar', this.closeAppSidebar)
	},
	beforeDestroy() {
		unsubscribe('tasks:close-appsidebar', this.closeAppSidebar)
	},
	created() {
		this.loadTask()
	},
	methods: {
		...mapActions([
			'deleteTask',
			'toggleCompleted',
			'toggleStarred',
			'setSummary',
			'setNote',
			'setPriority',
			'setPercentComplete',
			'setTags',
			'addTag',
			'setDue',
			'setStart',
			'toggleAllDay',
			'moveTask',
			'setClassification',
			'setStatus',
			'getTaskByUri',
			'togglePinned',
		]),

		async loadTask() {
			if (this.task === undefined || this.task === null) {
				const calendars = this.calendar ? [this.calendar] : this.calendars
				for (const calendar of calendars) {
					this.loading = true
					try {
						const task = await this.getTaskByUri({ calendar, taskUri: this.$route.params.taskId })
						// If we found the task, we don't need to query the other calendars.
						if (task) {
							break
						}
					} catch {
						console.debug('Task ' + this.$route.params.taskId + ' not found in calendar ' + calendar.displayName + '.')
					}
				}
				this.loading = false
			}
		},

		removeTask() {
			this.deleteTask({ task: this.task, dav: true })
			this.closeAppSidebar()
		},

		closeAppSidebar() {
			if (this.$route.params.calendarId) {
				this.$router.push({ name: 'calendars', params: { calendarId: this.$route.params.calendarId } })
			} else {
				this.$router.push({ name: 'collections', params: { collectionId: this.$route.params.collectionId } })
			}
		},

		editTitle(editing) {
			if (this.readOnly) {
				return
			}
			this.editingTitle = editing
			if (this.editingTitle) {
				this.newTitle = this.task.summary
			}
		},

		updateTitle(title) {
			this.newTitle = title
		},

		saveTitle() {
			if (this.newTitle !== this.task.summary) {
				this.setSummary({ task: this.task, summary: this.newTitle })
			}
		},

		/**
		 * Sets the start date to the given Date
		 * or to null
		 *
		 * @param {Task} task The task for which to set the date
		 * @param {Date} date The new start date
		 */
		setStartDate({ task, value: date }) {
			if (date) {
				date = moment(date)
			}
			if (this.task.startMoment.isSame(date)) {
				return
			}
			this.setStart({ task, start: date, allDay: this.allDay })
		},

		/**
		 * Sets the due date to the given Date
		 * or to null
		 *
		 * @param {Task} task The task for which to set the date
		 * @param {Date} date The new due date
		 */
		setDueDate({ task, value: date }) {
			if (date) {
				date = moment(date)
			}
			if (this.task.dueMoment.isSame(date)) {
				return
			}
			this.setDue({ task, due: date, allDay: this.allDay })
		},

		changeClass(classification) {
			this.setClassification({ task: this.task, classification: classification.type })
		},

		changeStatus(status) {
			this.setStatus({ task: this.task, status: status.type })
		},

		/**
		 * Sets the tags of a task
		 *
		 * @param {Array} tags The new tags
		 */
		updateTags(tags) {
			this.setTags({ task: this.task, tags })
		},

		/**
		 * Adds a tag to the list of tags
		 *
		 * @param {String} tag The name of the tag to add
		 */
		updateTag(tag) {
			this.addTag({ task: this.task, tag })
		},

		async changeCalendar(calendar) {
			const task = await this.moveTask({ task: this.task, calendar })
			// If we are in a calendar view, we have to navigate to the new calendar.
			if (this.$route.params.calendarId) {
				this.$router.push({ name: 'calendarsTask', params: { calendarId: task.calendar.id, taskId: task.uri } })
			}
		},
	},
}
</script>

<style lang="scss" scoped>
$red: #b3312d;
$yellow: #fd0;
$blue: #4271a6;

.app-sidebar-header__desc .app-sidebar-header__checkbox {
	display: flex;
	height: 44px;
	width: 44px;
	justify-content: center;

	input[type='checkbox'].checkbox {
		&:checked + label::before {
			background-image: var(--icon-checkmark-000) !important;
			background-color: unset;
			border-color: unset;
		}
		&:disabled + label::before {
			background-color: var(--color-background-darker) !important;
		}
		+ label {
			display: flex;
			align-items: center;

			&::before {
				margin: 0;
				border-width: 2px;
				border-radius: var(--border-radius);
				border-color: var(--color-border-dark);
				height: 14px;
				width: 14px;
			}

			&:hover {
				border-color: var(--color-border-dark);
			}

			&.priority {
				&--high::before {
					border-color: $red;
				}

				&--medium::before {
					border-color: $yellow;
				}

				&--low::before {
					border-color: $blue;
				}
			}
		}
	}
}

.app-sidebar::v-deep .app-sidebar-header__description {
	flex-wrap: wrap;
	margin: 0;
}

.app-sidebar::v-deep .app-sidebar-tabs {
	min-height: 160px !important;
}

.app-sidebar__tab {
	padding: 0;
	// This should go into @nextcloud/vue
	height: 100%;
}
</style>
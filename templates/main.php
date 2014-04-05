<div ng-app="Tasks" ng-cloak ng-controller="AppController" ng-click="closeAll()">
     <div id="task-lists" ng-controller="ListController">
    	<div id="task_lists_header" class="header" ng-class="{'search': status.searchActive}" ng-controller="SearchController">
        	<div id="main-toolbar">
            	<a id="search" ng-click="openSearch()" oc-click-focus="{selector: '#search-toolbar input', timeout: 0}">
                	<span class="icon search"></span>
                </a>
                <a id="loading" ng-click="update()">
                    <span class="loading" ng-class="{'done':!isLoading()}"></span>
                </a>
            </div>
            <div id="search-toolbar">
                <span class="icon menu-search"></span>
                <input type="text" key-placeholder="placeholder_search" placeholder="<?php p($l->t('Search...')); ?>" ng-model="searchString" ng-keyup="trySearch($event)" >
            	<a id="cancel-search" ng-click="closeSearch()">
                	<span class="icon detail-delete"></span>
                </a>
            </div>
        </div>
    	<div id="task_lists_scroll" class="scroll">
        	<ul id="collection_filters">
            	<li ng-repeat="collection in collections" id="collection_{{ collection.id }}" rel="{{ collection.id }}"
                    ng-class="{'animate-up': getCollectionCount(collection.id)<1, active: collection.id==route.listID}" oc-drop-task>
                	<a href="#/lists/{{ collection.id }}">
                		<span class="icon collection-{{ collection.id }}"><text ng-show="collection.id=='today'">{{ DOM }}</text></span>
                        <span class="count">{{ getCollectionString(collection.id) }}</span>
                		<span class="title"><text>{{ collection.displayname }}</text></span>
                    </a>
                </li>
            </ul>
            <ul id="collection_lists">
                <li ng-repeat="list in lists" id="list_{{ list.id }}" rel="{{ list.id }}" ng-class="{active: list.id==route.listID}" oc-drop-task>
                    <a href="#/lists/{{ list.id }}">
                        <span class="icon list-list"></span>
                        <span class="count"><text ng-show="getListCount(list.id,'all')">{{ getListCount(list.id,'all') }}</text></span>
                        <span class="title"><text ng-dblclick="editName(list.id)" oc-click-focus="{selector: 'input.edit', timeout: 0}">{{ list.displayname }}</text></span>
                    </a>
                    <input ng-model="list.displayname" class="edit" type="text" ng-show="route.listparameter=='name' && route.listID == list.id" stop-event="click"
                    ng-keydown="checkName($event)">
                </li>
            </ul>
            <a class="addlist" ng-click="startAddingList()" stop-event="click" oc-click-focus="{selector: '#newList', timeout: 0}">
                <span class="icon detail-add"></span>
                <span class="title"><text><?php p($l->t('Add List...')); ?></text></span>
                <input id="newList" ng-model="status.newListName" class="edit" type="text" ng-disabled="isAddingList" ng-show="status.addingList"
                stop-event="click" placeholder="<?php p($l->t('New List')); ?>" ng-keydown="checkListInput($event)" />
            </a>
        </div>
        <div id="task_lists_footer" class="footer">
        	<a class="delete" ng-click="deleteList(route.listID)" ng-show="showDelete(route.listID)">
            	<span class="icon detail-trash"></span>
            </a>
        </div>
    </div>

    <div id="task-tasks" ng-controller="TasksController" ng-class="{'details-visible':route.taskID}">
    	<div id="add-task" class="add-task" ng-show="showInput()" >
            <a class="input-star">
                <span class="icon input-star "></span>
            </a>
            <a class="input-date">
                <span class="icon input-date"></span>
            </a>
            <form ng-submit="addTask(taskName)" name="addTaskForm">
                <input id="target" ng-disabled="isAddingTask"  class="transparent" placeholder="{{ getAddString() }}" ng-model="taskName"
                ng-keydown="checkTaskInput($event)"/>
            </form>
        </div>
        <div class="task-list" ng-class="{'completed-hidden':!status.showhidden}" ng-switch="route.listID">
            <?php print_unescaped($this->inc('part.tasklist')); ?>
            <?php print_unescaped($this->inc('part.collectionall')); ?>
            <?php print_unescaped($this->inc('part.collectionweek')); ?>
        </div>
    </div>

    <div id="task-details" ng-include="'templates/part.details.php'" ng-class="{'details-visible':route.taskID}"></div>
</div>
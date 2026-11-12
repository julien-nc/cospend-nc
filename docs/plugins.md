# Cospend plugin system

Cospend can be extended by *UI plugins* provided by other Nextcloud apps. A plugin app
injects UI entry points into Cospend's interface (a navigation item, a "create a
project" menu action, a project context menu action, a project sidebar tab, a member
or bill context action, a bill list header action, a settings section…) and renders
its own Vue components inside Cospend's main content area, where they are free to call
Cospend's public API and the plugin app's own backend.

The plugin system is UI-only: a plugin app's backend stays completely independent.
No server-side support in Cospend is needed beyond the frontend contract described here.

# Overview

The design relies on three mechanisms:

* **Loading**: each plugin app is responsible for loading its own script(s) into
  Cospend's pages, by listening to a core Nextcloud event. Cospend has no server-side
  plugin discovery and no knowledge of the plugin's bundle structure.
* **Registration**: the script pushes a declarative, data-only registration object
  into `OCA.Cospend`.
* **Rendering**: entry points always end in a **Vue component wrapped in a Web
  Component** (`defineCustomElement`), rendered by Cospend in the right place, with
  data passed down as reactive properties. Cospend renders the element tag directly in
  its templates; thanks to custom element auto-upgrade, it does not need to know when
  or how the plugin defines the element.

The split of responsibilities is:

* **Cospend owns layout and mode** (which view is active, the list/details split,
  mobile behavior).
* **Plugins own their content** (everything inside their custom elements).

# Loading the plugin script

The core event `OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent` is emitted
before every AppFramework-rendered page, including Cospend's. A plugin app registers a
listener in its `appinfo/bootstrap.php` and injects its plugin script when a
Cospend page is rendered:

```php
<?php
// appinfo/bootstrap.php
declare(strict_types=1);

use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\Util;

class CospendPluginInjector implements OCP\EventDispatcher\IEventListener {
	public static function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}
		if ($event->getResponse()->getApp() !== 'cospend') {
			return;
		}
		// decide visibility here, e.g. skip public share pages:
		if (!$event->isLoggedIn()) {
			return;
		}
		Util::addScript('myapp', 'cospend-plugin');
	}
}

class Application implements IBootstrap {
	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(
			BeforeTemplateRenderedEvent::class,
			CospendPluginInjector::class
		);
	}
	public function boot(IBootContext $context): void {
	}
}
```

Notes:

* `Util::addScript()` automatically loads the plugin app's JavaScript translations, so
  `t('myapp', …)` works inside the plugin script and in the plugin components.
* The listener only exists while the plugin app is enabled, so a disabled app simply
  disappears from Cospend.
* The script is loaded on every Cospend page load. It should define the custom
  elements right away (see below) and push the registration object. The component
  *instances* are only created when the corresponding entry point is activated, but
  keep the bundle reasonable anyway: everything in it is downloaded and evaluated on
  every Cospend page load.
* Use the `myapp` app id in `t()` calls, never `cospend`.

# Registration

## The `OCA.Cospend` namespace

Cospend exposes a global `OCA.Cospend` object with:

| Member | Description |
| --- | --- |
| `state` | Cospend's current state (projects, members, options…). Read-only in spirit: never write to it, and prefer the data passed to your components. |
| `plugins` | The registration registry (array). |
| `registerPlugin(plugin)` | Register a plugin object. Idempotent by `id`. |

The plugin script must handle both load orders (it may execute before or
after Cospend's own `main.js`):

```js
// js/cospend-plugin.js — the plugin script loaded by the listener above
import { defineCustomElement } from 'vue'
import MyBalancesView from './MyBalancesView.vue'
import MyBalancesListView from './MyBalancesListView.vue'

// define the custom elements right away
customElements.define(
	'cospend-plugin-crossbalances',
	defineCustomElement(MyBalancesView, {
		props: ['projectId', 'pluginContext'],
	}),
)
customElements.define(
	'cospend-plugin-crossbalances-list',
	defineCustomElement(MyBalancesListView, {
		props: ['projectId', 'pluginContext'],
	}),
)

// register the plugin
const plugin = {
	id: 'crossbalances',
	appId: 'myapp',
	apiVersion: 1,
	entryPoints: { /* … */ },
}

if (window.OCA?.Cospend?.registerPlugin) {
	window.OCA.Cospend.registerPlugin(plugin)
} else {
	// Cospend is not loaded yet: adopt the namespace and store the registration,
	// Cospend will adopt it on boot.
	window.OCA.Cospend ??= {}
	window.OCA.Cospend.plugins ??= []
	window.OCA.Cospend.plugins.push(plugin)
}
```

Alternatively, the plugin script may wait for Cospend to be ready and emit
`cospend:plugin:register` on the [event bus](https://docs.nextcloud.com/server/latest/developer_manual/app_publishing_maintenance/app_development/event_bus.html):

```js
import { emit } from '@nextcloud/event-bus'
window.addEventListener('DOMContentLoaded', () => {
	emit('cospend:plugin:register', plugin)
})
```

Both paths converge on the same registry; Cospend emits `cospend:app:init` on the
event bus once it is mounted, in case your plugin needs a runtime setup hook.

## Registration object

```js
{
	// required
	id: 'crossbalances',       // unique plugin id
	appId: 'myapp',            // your app id, used for attribution and error reporting
	apiVersion: 1,             // contract version this plugin was written against

	// at least one entry point; unknown types are ignored with a console warning
	entryPoints: {
		navItem: { /* … */ },
		createProjectAction: { /* … */ },
		projectAction: { /* … */ },
		sidebarTab: { /* … */ },
		memberAction: { /* … */ },
		billAction: { /* … */ },
		billListAction: { /* … */ },
		settingsSection: { /* … */ },
	},
}
```

Each entry point value may also be an **array** of objects, if your plugin provides
several of the same type (e.g. multiple sidebar tabs).

Contract stability: event names and payload shapes, registration fields and entry
point semantics are **additive-only**. A breaking change of the contract bumps
`apiVersion`; plugins declaring an unsupported `apiVersion` are rejected with a
console warning.

## Custom element rules (applies to every entry point)

Every entry point ends in the same leaf contract: a `view` field holding the tag name
of a custom element, **which must start with `cospend-plugin-`** (custom element names
require a dash anyway; the prefix lets Cospend treat these tags as custom elements in
its Vue templates). Entry points that can be displayed as `content` (see
[Layout](#layout-while-a-plugin-view-is-active)) may also declare an optional
`listView` field with a second element tag name.

* The element must be defined by the plugin script (with `defineCustomElement`, see
  above). How the plugin app organizes its bundle is its own business — it may even
  `import()` its component chunks dynamically from the plugin script, Cospend does not
  care when the element gets defined: custom elements upgrade automatically, so
  Cospend renders the tag unconditionally and the element takes over once defined.
* If the element is not defined when its entry point first renders (e.g. it is
  defined from a dynamic `import()`), the host waits for the definition and
  re-renders once the element is available. If it is never defined (broken
  plugin), Cospend shows an error empty content instead of a silently blank area.
* The view component must be a Vue component wrapped with `defineCustomElement`
  (the plugin's own Vue version is embedded inside the element, so there is no runtime
  coupling with Cospend).
* The element's Vue app is a separate app, so the global `t()`/`n()` translation
  helpers are not available unless you provide them. Use the `configureApp` hook:

```js
defineCustomElement(MyView, {
	props: ['projectId', 'pluginContext'],
	configureApp(app) {
		app.config.globalProperties.t = window.t
		app.config.globalProperties.n = window.n
	},
})
```

Data flows **into** the element as reactive properties:

| Property | Type | Description |
| --- | --- | --- |
| `projectId` | string \| null | The project the entry point was activated with, `null` for app-level entry points. |
| `pluginContext` | object \| null | A plain snapshot of Cospend's state, recomputed on every change (see below). |

`pluginContext` contains:

| Key | Description |
| --- | --- |
| `user` | Current user info, `null` on public pages. |
| `pageIsPublic` | Whether the current page is a public share page. |
| `projects` | All projects of the current user, keyed by project id. |
| `members` | All members, keyed by project id, then member id. |
| `project` | The project object, for project-scoped entry points. |
| `accessLevel` | The current user's access level on the project, same scope. |
| `member` | The member object, for member-scoped entry points (`memberAction`). |
| `bill` | The bill object, for bill-scoped entry points (`billAction`). |

The snapshot is scoped to the entry point (`project`, `member` and `bill` are only
set for the matching scope) and is a **plain object**, not a reactive proxy:
reassigning it triggers a re-render of
your element. `App.vue` is the single source of truth; when Cospend's state changes,
the property is updated and your component re-renders. You never need to read
`OCA.Cospend.state` and never need to subscribe to change events.

# Entry points

Icons are rendered at 20px through `NcIconSvgWrapper`. Like the Material Design icons
used by Cospend itself, plugin icon artwork should include inner margins in its
viewBox so it does not look bigger than the core icons.

## Layout while a plugin view is active

Cospend owns the layout: activating an entry point switches Cospend to the plugin's
view, and the content is placed by Cospend, never by the plugin.

* `display: 'modal'` opens the plugin's element in a dialog and does not touch the
  main layout.
* `display: 'content'` replaces the main content area (the details pane) with the
  plugin's element. The list pane then shows the plugin's `listView` element if the
  entry point declares one, and keeps Cospend's default content otherwise (the bill
  list when a project is selected, nothing for app-level entry points like `navItem`).

| Entry point | Main content | List pane |
| --- | --- | --- |
| `navItem` | plugin `view` | plugin `listView` if declared, else empty |
| `createProjectAction` | plugin `view` | plugin `listView` if declared, else empty |
| `projectAction` | plugin `view` | plugin `listView` if declared, else bill list |
| `sidebarTab` | sidebar tab content | unchanged |
| `memberAction` | plugin `view` | plugin `listView` if declared, else bill list |
| `billAction` | plugin `view` | plugin `listView` if declared, else bill list |
| `billListAction` | plugin `view` | plugin `listView` if declared, else bill list |
| `settingsSection` | settings section content | unchanged |

On mobile, the usual list/details toggle applies, driven by Cospend.

## `navItem`

Adds an item to the bottom of Cospend's navigation (like "Cospend settings"). Clicking
it replaces the main content area with the plugin's view, in Cospend's list/details
layout:

```js
navItem: {
	// optional: distinguishes several navItems of the same plugin
	// (defaults to the view tag)
	// id: 'myapp-balances',
	name: 'Cross-project balances',
	icon: '/apps/myapp/img/icon.svg',   // SVG url, data URI or inline SVG string
	order: 60,                          // suggested position, lower comes first
	view: 'cospend-plugin-crossbalances',
	// optional: a second element rendered in the list pane of the two-pane layout.
	// Without it, the view takes the full content width (like "Project statistics").
	listView: 'cospend-plugin-crossbalances-list',
}
```

## `createProjectAction`

Adds an action in the "Create a project" menu in the navigation search bar (where
"Create empty project", "Import csv project" and "Import SplitWise project" live).
Like `navItem`, it is app-level: it has no project context, so `minAccessLevel` does
not apply, and the current project (if any) is deselected when the view is activated:

```js
createProjectAction: {
	id: 'myapp-create-from-template',
	name: 'Create a project from a template',
	icon: '/apps/myapp/img/action-icon.svg',
	display: 'modal',        // 'content': replaces the main view; 'modal': opens a dialog
	view: 'cospend-plugin-create-from-template',
}
```

The element receives `projectId` (always `null`) and `pluginContext` (with all
projects and members, but no `project`). Creating the project itself is done by
firing the `cospend:create-project` action event (see
[Interacting with Cospend](#interacting-with-cospend)); when the creation succeeds,
Cospend selects the new project. The view stays under the plugin's control: observe
the outcome through the `pluginContext` prop and close the view with the
`cospend:close-plugin-view` action event when it has fulfilled its purpose.
With `display: 'content'`,
you can optionally declare `listView` to fill the list pane of the two-pane layout
while the view is active.

## `projectAction`

Adds an action in the project context menu (where "Statistics" and "Project
settlement" live):

```js
projectAction: {
	id: 'myapp-extended-settle',
	name: 'Extended settlement',
	icon: '/apps/myapp/img/action-icon.svg',
	display: 'content',      // 'content': replaces the main view; 'modal': opens a dialog
	minAccessLevel: 2,       // optional: 1 viewer, 2 participant, 3 maintainer, 4 admin
	view: 'cospend-plugin-extended-settle',
}
```

The element receives `projectId` and `pluginContext` (with `project`, `members` and
`accessLevel` set). With `display: 'content'`, you can optionally declare `listView`
to also take over the bill list pane while the view is active (see
[Layout](#layout-while-a-plugin-view-is-active)).

`minAccessLevel` uses the same scale as the project access levels (1 viewer,
2 participant, 3 maintainer, 4 admin). It is optional and defaults to 1 (viewer).
Entry points of app-level types (`navItem`, `createProjectAction`, `settingsSection`)
have no project context and ignore it.

## `sidebarTab`

Adds a tab in the project sidebar (where "Sharing", "Settings", "Categories"… live).
The tab content is your element:

```js
sidebarTab: {
	id: 'myapp-budget',      // unique tab id, prefix it with your app id
	name: 'Budget',
	icon: '/apps/myapp/img/tab-icon.svg',
	order: 10,               // core tabs use 1 to 6, use 10+ to come after them
	minAccessLevel: 2,       // optional: 1 viewer, 2 participant, 3 maintainer, 4 admin
	view: 'cospend-plugin-budget',
}
```

The element receives `projectId` and `pluginContext` (with `project`, `members` and
`accessLevel` set). The tab appears for every selected project, regardless of the
active main view.

## `memberAction`

Adds an action in a member's context menu in the navigation:

```js
memberAction: {
	id: 'myapp-member-history',
	name: 'Detailed member history',
	icon: '/apps/myapp/img/member-action-icon.svg',
	display: 'modal',        // 'content': replaces the main view; 'modal': opens a dialog
	minAccessLevel: 1,       // optional: 1 viewer, 2 participant, 3 maintainer, 4 admin
	view: 'cospend-plugin-member-history',
}
```

The element receives `projectId` and `pluginContext` (with `project`, `members`,
`member` and `accessLevel` set). With `display: 'content'`, you can optionally declare
`listView` to also take over the bill list pane while the view is active (see
[Layout](#layout-while-a-plugin-view-is-active)).

## `billAction`

Adds an action in a bill's context menu in the bill list. Shown in the bill item's
action menu when the user has edition access, not in selection mode, and not on
not-yet-saved or trash bin bills:

```js
billAction: {
	id: 'myapp-bill-split',
	name: 'Split this bill',
	icon: '/apps/myapp/img/bill-action-icon.svg',
	display: 'modal',        // 'content': replaces the main view; 'modal': opens a dialog
	minAccessLevel: 1,       // optional: 1 viewer, 2 participant, 3 maintainer, 4 admin
	view: 'cospend-plugin-bill-split',
}
```

The element receives `projectId` and `pluginContext` (with `project`, `members`,
`bill` and `accessLevel` set). With `display: 'content'`, you can optionally declare
`listView` to also take over the bill list pane while the view is active (see
[Layout](#layout-while-a-plugin-view-is-active)).

## `billListAction`

Adds an action in the bill list header actions menu (where "Show the trash bin",
"Filters" and multi-selection live). The action is scoped to the whole bill list, not
to a single bill:

```js
billListAction: {
	id: 'myapp-list-report',
	name: 'Generate a report',
	icon: '/apps/myapp/img/list-action-icon.svg',
	display: 'content',      // 'content': replaces the main view; 'modal': opens a dialog
	minAccessLevel: 1,       // optional: 1 viewer, 2 participant, 3 maintainer, 4 admin
	view: 'cospend-plugin-list-report',
}
```

The element receives `projectId` and `pluginContext` (with `project`, `members` and
`accessLevel` set), but no specific bill or member. With `display: 'content'`, you can
optionally declare `listView` to also take over the bill list pane while the view is
active (see [Layout](#layout-while-a-plugin-view-is-active)).

## `settingsSection`

Adds a section in Cospend's settings dialog. Persist your settings through your own
app's backend:

```js
settingsSection: {
	id: 'myapp-settings',
	name: 'My plugin',
	icon: '/apps/myapp/img/settings-icon.svg',
	view: 'cospend-plugin-settings',
}
```

# Interacting with Cospend

## Action events (plugin → Cospend)

Fire and forget action events, emitted on the event bus. They are routed to the same
code
paths Cospend itself uses, so Cospend updates its state, refreshes its UI and displays
errors when applicable. Observe the outcome through the `pluginContext` prop, which is
updated after Cospend's state changes.

These events are not plugin-specific duplicates: they *are* the events Cospend's own
interface emits (project items, bill list items, member items, the create project
menu…). Cospend's internal components and plugin apps emit the exact same event names
with the same payload shapes, and Cospend handles them with a single set of handlers.
Like all Cospend event bus events, their names carry the `cospend:` app prefix.

| Event | Payload | Effect |
| --- | --- | --- |
| `cospend:select-project` | `{ projectId }` | Select the project (closes the plugin view). |
| `cospend:deselect-project` | `{}` | Deselect the current project (closes the plugin view). |
| `cospend:close-plugin-view` | `{ pluginId, entryPointId? }` | Close the plugin's active view (modal or main content view). Only the plugin's own views can be closed. |
| `cospend:refresh-project` | `{ projectId }` | Reload project info, members and balances. |
| `cospend:create-project` | `{ name, id? }` | Create a project (the id is optional and defaults to the slugified name) and select it. |
| `cospend:create-bill` | `{ projectId, bill }` | Create a bill. |
| `cospend:save-bill` | `{ projectId, bill }` | Update an existing bill (the bill must be in the displayed bill list, identified by `bill.id`). |
| `cospend:delete-bill` | `{ projectId, billId }` | Delete a bill from the displayed bill list (it goes to the trash bin). |
| `cospend:restore-bill` | `{ projectId, billId }` | Restore a bill from the trash bin (it must be in the displayed trash bin list). |
| `cospend:save-member` | `{ projectId, member }` | Create or update a member. |
| `cospend:delete-member` | `{ projectId, memberId }` | Deactivate a member (same as the "Delete" member action). |
| `cospend:archive-project` | `{ projectId }` | Toggle the project's archive state. |

Example:

```js
import { emit } from '@nextcloud/event-bus'

emit('cospend:create-bill', { projectId, bill: { /* bill object */ } })
```

## Lifecycle (Cospend → plugin)

| Event | Payload | Meaning |
| --- | --- | --- |
| `cospend:app:init` | `{}` | Cospend is mounted (late registration/setup hook). |
| `cospend:app-view-changed` | `{ pluginId, entryPointId, projectId, active }` | The active view changed; `active: false` means your view was closed. |

## Calling APIs

Your components can freely call Cospend's public OCS API (session authenticated) and
your own app's API. Requests that mutate Cospend data should go through the action
events above so that Cospend's state stays consistent.

# Reference implementation

`src/demoPlugin/` contains a complete demo plugin, written exactly like a plugin
coming from an external app: it defines custom elements for all eight entry point
types, reads everything from its `pluginContext` prop and interacts with Cospend only
through action events. It is injected by the main page controller when the
"Enable the demo UI plugin" admin setting is enabled.

Note for plugin authors building their views with Vue: `defineCustomElement` puts the
component in a shadow DOM root by default, which is self-contained (styles collected
at build time only with the `customElement` build flag of the Vue plugin). To keep it
simple, the demo plugin uses `shadowRoot: false` (light DOM custom elements), so the
regular document styles apply.

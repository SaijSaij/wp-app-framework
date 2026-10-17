/**
 * The API of `@webcodingcat/wp-app-framework/settings`, for settings scripts (`*.settings.js` next to a settings group
 * class), which run on the settings page of your theme or plugin.
 *
 * The import is no module of its own: the webpack helper maps it to the settings page of your theme or plugin,
 * `wpAppFramework[slug].settingsApi`. These declarations describe that object for your IDE.
 *
 * @see guides/settings.md#controls-of-your-own
 */

import type { Component } from './index';

export type { Component } from './index';

/** The value of a settings group, by setting name. */
export type GroupValue = Record<string, unknown>;

/** Props of a control registered with `registerSettingsControl()`. */
export interface SettingsControlProps<V = unknown> {
	/** The value the control replaces: a setting, a field of a value class or an item of a list. */
	value: V;
	/** Sets the value. */
	onChange: (value: V) => void;
	/** Where the value is in the group: the setting, the fields and, for list items, their index, e.g. `['schemes', 0]`. */
	path: Array<string | number>;
	/** The value of the whole group, with the changes not saved yet. */
	groupValue: GroupValue;
	/** The control the settings page would show without yours; render it to add to it instead of replacing it. */
	DefaultControl: Component<Record<string, never>>;
}

/**
 * Replaces the control of a value of a settings group on the settings page: a setting, a field of a value class in
 * it or each item of a list.
 *
 * @param group     The name of the group, as in `#[SettingsGroup(name: …)]`.
 * @param path      The setting, then the fields below it and `*` for the items of a list: `'schemes'`,
 *                  `['schemes', '*']`, `['contact', 'email']`. A string names a setting.
 * @param component The control; it receives the value, `onChange`, the path with list indices, the value of the group
 *                  and the generated control as `DefaultControl`.
 */
export function registerSettingsControl<V = unknown>(group: string, path: string | string[], component: Component<SettingsControlProps<V>>): void;

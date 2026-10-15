/**
 * The editor API of `@saij/wp-app-framework`, for editor scripts of a theme or plugin (`*.editor.js` next to a
 * block or block support class, or in `assets/`).
 *
 * The import is no module of its own: the webpack helper maps it to the editor runtime of your theme or plugin,
 * `wpAppFramework[slug].api`. These declarations describe that object for your IDE.
 *
 * @see ../docs/content/guides/editor.md
 */

/** A React component: a function or a class that takes the props `P`. */
export type Component<P> = ((props: P) => unknown) | (new (props: P) => unknown);

/** The attributes of a block, by attribute name. */
export type Attributes = Record<string, unknown>;

/** Props that every component registered with the editor API receives. */
export interface BlockProps {
	/** The attributes of the block. */
	attributes: Attributes;
	/** Changes attributes of the block, like `setAttributes` of a block's `edit` component in WordPress. */
	setAttributes: (attributes: Attributes) => void;
	/** The client ID of the block in the editor. */
	clientId: string;
	/** Whether the block shows its front-end preview (toolbar button "Preview") instead of the editable fields. */
	isPreview: boolean;
}

/** Props of a control registered with `registerControl()` or `registerSupportControl()`. */
export interface ControlProps<V = unknown> extends BlockProps {
	/** The value of the attribute the control replaces. */
	value: V;
	/** Sets the value of the attribute. */
	onChange: (value: V) => void;
}

/** Props of a panel registered with `registerPanel()`. */
export type PanelProps = BlockProps;

/** Props of a toolbar button registered with `registerToolbarButton()`. */
export type ToolbarButtonProps = BlockProps;

/** A JSON value, as set in PHP with `ViewData::set()`. */
export type DataValue = null | boolean | number | string | DataValue[] | { [key: string]: DataValue };

/**
 * Replaces the generated inspector control of an attribute of a block: a top-level attribute of its attribute
 * class or the attribute of a block support the block uses. It wins over `registerSupportControl()`.
 *
 * @param block     The block name without namespace, e.g. `hero`; the namespace of your theme or plugin is added.
 * @param attribute The name of the attribute.
 * @param component The control; it receives the value of the attribute, `onChange` and the block props.
 */
export function registerControl<V = unknown>(block: string, attribute: string, component: Component<ControlProps<V>>): void;

/**
 * Replaces the generated control of a block support (`#[BlockSupport]`) in every block of your theme or plugin that
 * uses it.
 *
 * @param attribute The attribute of the block support, as in `#[BlockSupport(attribute: …)]`.
 * @param component The control; it receives the value of the attribute, `onChange` and the block props.
 */
export function registerSupportControl<V = unknown>(attribute: string, component: Component<ControlProps<V>>): void;

/**
 * Adds a panel to the inspector of a block, after the generated controls. Shown in the editing mode `default` only.
 *
 * @param block     The block name without namespace, e.g. `hero`.
 * @param component The panel; it receives the block props. Render a `PanelBody` of `@wordpress/components`.
 */
export function registerPanel(block: string, component: Component<PanelProps>): void;

/**
 * Adds a button to the toolbar of a block.
 *
 * @param block     The block name without namespace, e.g. `hero`.
 * @param component The button; it receives the block props. Render a `ToolbarButton` of `@wordpress/components`.
 */
export function registerToolbarButton(block: string, component: Component<ToolbarButtonProps>): void;

/**
 * Loads an external script, such as the library of a map service, once per URL and document.
 *
 * @param url        An `https:` URL or a protocol-relative one (`//…`).
 * @param attributes Further attributes of the script element, such as `integrity` or `crossorigin`; `true` for an
 *                   attribute without value.
 * @returns Fulfilled once the script has loaded; rejected if it fails to load.
 */
export function loadScript(url: string, attributes?: Record<string, string | boolean>): Promise<void>;

/**
 * A value of the view data of your theme or plugin, set in PHP with `ViewData::set()`.
 *
 * @param key The key of the value.
 * @returns The value; an unknown key throws an error that names the known keys.
 */
export function getData(key: string): DataValue;

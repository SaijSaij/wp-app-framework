/**
 * ESLint configuration: the defaults of `@wordpress/scripts`.
 *
 * WordPress provides these packages to the editor and admin pages at runtime (script handles,
 * mapped by the dependency extraction plugin); they are not installed.
 */
const defaults = require( '@wordpress/scripts/config/eslint.config.cjs' );

const PROVIDED_BY_WORDPRESS = [
	'@wordpress/api-fetch',
	'@wordpress/block-editor',
	'@wordpress/blocks',
	'@wordpress/components',
	'@wordpress/compose',
	'@wordpress/core-data',
	'@wordpress/data',
	'@wordpress/date',
	'@wordpress/dom-ready',
	'@wordpress/editor',
	'@wordpress/element',
	'@wordpress/i18n',
	'@wordpress/media-utils',
	'@wordpress/notices',
	'@wordpress/plugins',
];

module.exports = [
	...defaults,
	{
		settings: {
			'import/core-modules': PROVIDED_BY_WORDPRESS,
		},
	},
	{
		// Command line scripts of the documentation report on the console.
		files: [ 'docs/scripts/**' ],
		rules: {
			'no-console': 'off',
		},
	},
];

/**
 * ESLint configuration: the defaults of @wordpress/scripts.
 *
 * WordPress provides these packages to the editor at runtime (script handles,
 * mapped by the dependency extraction plugin); they are not installed.
 */
const defaults = require( '@wordpress/scripts/config/eslint.config.cjs' );

const PROVIDED_BY_WORDPRESS = [
	'@wordpress/api-fetch',
	'@wordpress/block-editor',
	'@wordpress/blocks',
	'@wordpress/components',
	'@wordpress/data',
	'@wordpress/element',
];

module.exports = [
	...defaults,
	{
		settings: {
			'import/core-modules': PROVIDED_BY_WORDPRESS,
		},
	},
];

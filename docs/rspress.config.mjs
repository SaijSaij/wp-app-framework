import fs from 'node:fs';
import { defineConfig } from '@rspress/core';

// Each version is built for its own directory on GitHub Pages: /wp-app-framework/<version>/
// (see scripts/publish.mjs). CI must say which one; locally it defaults to "next".
const base = process.env.DOCS_BASE ?? defaultBase();

function defaultBase() {
	if ( process.env.CI ) {
		throw new Error(
			'DOCS_BASE is not set, e.g. DOCS_BASE=/wp-app-framework/next/.'
		);
	}
	return '/wp-app-framework/next/';
}

/**
 * A sidebar part of the API reference, written by the generators before the build (npm run api).
 *
 * @param {string} name File name in .generated/.
 * @return {Object[]} Sidebar groups.
 */
function generatedSidebar( name ) {
	const file = new URL( `./.generated/${ name }`, import.meta.url );
	if ( ! fs.existsSync( file ) ) {
		throw new Error(
			`.generated/${ name } is missing; run "npm run api" (part of "npm run build" and "npm run dev").`
		);
	}
	return JSON.parse( fs.readFileSync( file, 'utf8' ) );
}

export default defineConfig( {
	root: 'content',
	outDir: 'build',
	base,
	title: 'wp-app-framework',
	description:
		'PHP/JS framework for full site editing themes, child themes and plugins for WordPress 7.',
	globalStyles: new URL( './theme/styles.css', import.meta.url ).pathname,
	markdown: {
		// Anchors to pages compiled later are not reported by Rspress 2.0.23;
		// scripts/check-links.mjs checks every link of the built site.
		link: { checkDeadLinks: true, checkAnchors: true },
		shiki: { langs: [ 'php', 'twig', 'json', 'js', 'jsx', 'sh' ] },
	},
	themeConfig: {
		editLink: {
			docRepoBaseUrl:
				'https://github.com/saijsaij/wp-app-framework/tree/main/docs/content',
		},
		nav: [
			{
				text: 'Guide',
				link: '/',
				activeMatch: '^/(?!api/|development/)',
			},
			{ text: 'API reference', link: '/api/', activeMatch: '^/api/' },
			{
				text: 'Development',
				link: '/development/',
				activeMatch: '^/development/',
			},
		],
		socialLinks: [
			{
				icon: 'github',
				mode: 'link',
				content: 'https://github.com/saijsaij/wp-app-framework',
			},
		],
		sidebar: {
			'/': [
				{
					text: 'Introduction',
					items: [
						{ text: 'Overview', link: '/' },
						{ text: 'Concepts', link: '/concepts' },
						{ text: 'Getting started', link: '/getting-started' },
					],
				},
				{
					text: 'Guides',
					items: [
						{ text: 'Boot and modes', link: '/guides/boot' },
						{ text: 'Services', link: '/guides/services' },
						{ text: 'Hooks', link: '/guides/hooks' },
						{ text: 'REST routes', link: '/guides/rest' },
						{ text: 'WP-CLI commands', link: '/guides/cli' },
						{ text: 'Twig templates', link: '/guides/twig' },
						{ text: 'Blocks', link: '/guides/blocks' },
						{
							text: 'Container blocks',
							link: '/guides/containers',
						},
						{ text: 'Block supports', link: '/guides/supports' },
						{ text: 'Editor', link: '/guides/editor' },
						{
							text: 'Front-end scripts',
							link: '/guides/frontend',
						},
						{ text: 'Styles', link: '/guides/styles' },
						{ text: 'Static assets', link: '/guides/assets' },
						{ text: 'Settings', link: '/guides/settings' },
						{ text: 'Posts', link: '/guides/posts' },
						{ text: 'Queries', link: '/guides/queries' },
						{ text: 'Tables', link: '/guides/tables' },
						{ text: 'Cache', link: '/guides/cache' },
						{ text: 'Cron events and tasks', link: '/guides/cron' },
						{
							text: 'Activation and uninstallation',
							link: '/guides/lifecycle',
						},
						{ text: 'Values', link: '/guides/values' },
						{ text: 'Translations', link: '/guides/translations' },
						{ text: 'Plugins', link: '/guides/plugins' },
						{ text: 'Child themes', link: '/guides/child-themes' },
					],
				},
				{
					text: 'Build and release',
					items: [
						{ text: 'Build', link: '/build-and-release/build' },
						{ text: 'Release', link: '/build-and-release/release' },
						{
							text: 'Releases and CI',
							link: '/build-and-release/ci',
						},
					],
				},
				{
					text: 'Reference',
					items: [
						{ text: 'API reference', link: '/api/' },
						{ text: 'Upgrading', link: '/reference/upgrading' },
					],
				},
			],
			'/development/': [
				{
					text: 'Developing the framework',
					items: [
						{ text: 'Overview', link: '/development/' },
						{
							text: 'Architecture',
							link: '/development/architecture',
						},
						{
							text: 'Design decisions',
							link: '/development/design',
						},
						{
							text: 'Conventions',
							link: '/development/conventions',
						},
						{ text: 'Testing', link: '/development/testing' },
						{
							text: 'Pipelines and releases',
							link: '/development/pipelines',
						},
						{
							text: 'Adding a feature',
							link: '/development/adding-a-feature',
						},
					],
				},
			],
			'/api/': [
				{ text: 'Overview', link: '/api/' },
				...generatedSidebar( 'api-php.json' ),
				...generatedSidebar( 'api-js.json' ),
				{
					text: 'Configuration and commands',
					collapsible: true,
					collapsed: false,
					items: [
						{ text: 'Hooks', link: '/api/hooks' },
						{ text: 'composer.json', link: '/api/composer' },
						{ text: 'Commands', link: '/api/commands' },
						{ text: 'Webpack helper', link: '/api/webpack' },
						{ text: 'Release workflow', link: '/api/workflow' },
					],
				},
			],
		},
	},
} );

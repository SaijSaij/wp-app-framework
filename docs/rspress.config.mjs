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
						{ text: 'Twig templates', link: '/guides/twig' },
						{ text: 'Blocks', link: '/guides/blocks' },
						{ text: 'Block supports', link: '/guides/supports' },
						{ text: 'Editor', link: '/guides/editor' },
						{ text: 'Styles', link: '/guides/styles' },
						{ text: 'Settings', link: '/guides/settings' },
						{ text: 'Values', link: '/guides/values' },
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
						{ text: 'Upgrading', link: '/reference/upgrading' },
					],
				},
			],
		},
	},
} );

/**
 * Custom theme: the default theme of Rspress with the version switcher next to
 * the title and the version banner above the page content.
 */
import { Layout as BasicLayout } from '@rspress/core/theme-original';
import { VersionBanner, VersionSwitcher } from './versions';

export function Layout() {
	return (
		<BasicLayout
			afterNavTitle={ <VersionSwitcher /> }
			beforeDocContent={ <VersionBanner /> }
		/>
	);
}

export * from '@rspress/core/theme-original';

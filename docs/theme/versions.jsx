/**
 * Version switcher and banner.
 *
 * Every version of the documentation is a separate build in its own directory
 * (/wp-app-framework/<version>/). versions.json in the parent directory lists
 * the published versions (written by scripts/publish.mjs); it is read at
 * runtime, so older builds offer the versions published after them as well.
 */
import { useEffect, useState } from 'react';
import { withBase } from '@rspress/core/runtime';

/**
 * @typedef {Object} Versions
 * @property {string|null} latest   Newest released version, e.g. "0.3".
 * @property {boolean}     next     Whether "next" (unreleased changes) exists.
 * @property {string[]}    versions Released versions, newest first.
 */

function useVersions() {
	const base = withBase( '/' );
	const root = base.replace( /[^/]+\/$/, '' );
	const current = base.slice( root.length, -1 );
	const [ versions, setVersions ] = useState(
		/** @type {Versions|null} */ ( null )
	);

	useEffect( () => {
		let active = true;
		fetch( root + 'versions.json' )
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error(
						`${ root }versions.json: HTTP ${ response.status }`
					);
				}
				return response.json();
			} )
			.then( ( data ) => active && setVersions( data ) )
			.catch( ( error ) => {
				// Only a local build without published versions gets here.
				// eslint-disable-next-line no-console
				console.info( 'Version list unavailable:', error.message );
			} );
		return () => {
			active = false;
		};
	}, [ root ] );

	return { base, root, current, versions };
}

export function VersionSwitcher() {
	const { base, root, current, versions } = useVersions();
	const list = versions
		? [ ...( versions.next ? [ 'next' ] : [] ), ...versions.versions ]
		: [ current ];

	return (
		<select
			className="version-switcher"
			aria-label="Documentation version"
			value={ current }
			onChange={ ( event ) => {
				// The same page in the selected version.
				window.location.href =
					root +
					event.target.value +
					'/' +
					window.location.pathname.slice( base.length ) +
					window.location.hash;
			} }
		>
			{ list.map( ( version ) => (
				<option key={ version } value={ version }>
					{ version }
				</option>
			) ) }
		</select>
	);
}

export function VersionBanner() {
	const { root, current, versions } = useVersions();

	if ( current === 'next' ) {
		return (
			<div className="version-banner" role="note">
				This documentation describes unreleased changes on the main
				branch.
				{ versions?.latest && (
					<>
						{ ' ' }
						<a href={ `${ root }${ versions.latest }/` }>
							Documentation of the latest release (
							{ versions.latest })
						</a>
					</>
				) }
			</div>
		);
	}

	if ( versions?.latest && current !== versions.latest ) {
		return (
			<div className="version-banner" role="note">
				This documentation describes version { current }.{ ' ' }
				<a href={ `${ root }${ versions.latest }/` }>
					Documentation of the latest release ({ versions.latest })
				</a>
			</div>
		);
	}

	return null;
}

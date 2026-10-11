import { describe, expect, it, vi } from 'vitest';

import { createRenderer } from '../renderer';

const tick = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

describe( 'renderer prefetch', () => {
	it( 'sends prefetched blocks in the same batch and serves them from the cache', async () => {
		let respond;
		const fetch = vi.fn(
			() => new Promise( ( resolve ) => ( respond = resolve ) )
		);
		const renderer = createRenderer( { fetch, path: '/r', maxBlocks: 10 } );

		const container = renderer.render( 'container', { name: 'c' } );
		renderer.prefetch( 'inner', { name: 'i' } );
		renderer.prefetch( 'inner', { name: 'i' } );
		await tick();
		expect( fetch ).toHaveBeenCalledTimes( 1 );
		expect( fetch.mock.calls[ 0 ][ 0 ].data.blocks ).toEqual( [
			{ name: 'c' },
			{ name: 'i' },
		] );

		// The inner block mounts while the request runs and joins it.
		const inner = renderer.render( 'inner', { name: 'i' } );
		container.cancel();
		expect( fetch.mock.calls[ 0 ][ 0 ].signal.aborted ).toBe( false );

		respond( {
			results: [ { html: '<div></div>' }, { html: '<p></p>' } ],
		} );
		await expect( inner.promise ).resolves.toBe( '<p></p>' );
		expect( renderer.peek( 'inner' ) ).toBe( '<p></p>' );
	} );
} );

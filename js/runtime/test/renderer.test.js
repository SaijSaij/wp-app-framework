import { describe, it, expect, vi } from 'vitest';

import { createRenderer } from '../renderer';

function fakeFetch() {
	const calls = [];
	const fetch = vi.fn( ( request ) => {
		const call = { request, aborted: false };
		request.signal.addEventListener( 'abort', () => {
			call.aborted = true;
		} );
		calls.push( call );
		return new Promise( ( resolve, reject ) => {
			call.resolve = resolve;
			call.reject = reject;
		} );
	} );
	return { fetch, calls };
}

const tick = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

describe( 'renderer', () => {
	it( 'sends all requests of a task in one batch and caches the results', async () => {
		const { fetch, calls } = fakeFetch();
		const renderer = createRenderer( { fetch, path: '/r', maxBlocks: 10 } );

		const a = renderer.render( 'a', { name: 'x/a' } );
		const b = renderer.render( 'b', { name: 'x/b' } );
		const again = renderer.render( 'a', { name: 'x/a' } );
		await tick();

		expect( calls ).toHaveLength( 1 );
		expect( calls[ 0 ].request.data ).toEqual( {
			blocks: [ { name: 'x/a' }, { name: 'x/b' } ],
		} );
		expect( calls[ 0 ].request.method ).toBe( 'POST' );
		calls[ 0 ].resolve( {
			results: [
				{ html: '<p>a</p>' },
				{ error: { code: 'c', message: 'failed' } },
			],
		} );

		await expect( a.promise ).resolves.toBe( '<p>a</p>' );
		await expect( again.promise ).resolves.toBe( '<p>a</p>' );
		await expect( b.promise ).rejects.toMatchObject( {
			message: 'failed',
			code: 'c',
		} );
		expect( renderer.peek( 'a' ) ).toBe( '<p>a</p>' );
		expect( renderer.peek( 'b' ) ).toBeUndefined();

		await expect(
			renderer.render( 'a', { name: 'x/a' } ).promise
		).resolves.toBe( '<p>a</p>' );
		renderer.render( 'b', { name: 'x/b' } );
		await tick();
		expect( calls ).toHaveLength( 2 );
	} );

	it( 'splits batches at the maximum size', async () => {
		const { fetch, calls } = fakeFetch();
		const renderer = createRenderer( { fetch, path: '/r', maxBlocks: 2 } );
		[ 'a', 'b', 'c' ].forEach( ( key ) =>
			renderer.render( key, { name: key } )
		);
		await tick();

		expect(
			calls.map( ( call ) => call.request.data.blocks.length )
		).toEqual( [ 2, 1 ] );
	} );

	it( 'drops unsent requests nobody waits for and aborts a batch only when all its callers cancelled', async () => {
		const { fetch, calls } = fakeFetch();
		const renderer = createRenderer( { fetch, path: '/r', maxBlocks: 10 } );

		renderer.render( 'gone', { name: 'gone' } ).cancel();
		const a = renderer.render( 'a', { name: 'a' } );
		const b = renderer.render( 'b', { name: 'b' } );
		await tick();
		expect( calls[ 0 ].request.data.blocks ).toEqual( [
			{ name: 'a' },
			{ name: 'b' },
		] );

		a.cancel();
		a.cancel();
		expect( calls[ 0 ].aborted ).toBe( false );

		// Wanted again while in flight: the batch must not be aborted.
		const aAgain = renderer.render( 'a', { name: 'a' } );
		b.cancel();
		expect( calls[ 0 ].aborted ).toBe( false );

		aAgain.cancel();
		expect( calls[ 0 ].aborted ).toBe( true );
	} );
} );

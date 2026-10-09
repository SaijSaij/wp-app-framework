/**
 * Renders blocks through the batch route of the instance.
 *
 * All requests made in the same task are sent together (chunks of maxBlocks).
 * Successful results are cached by key for the lifetime of the editor page,
 * so undo/redo and duplicated blocks need no request. Errors are not cached.
 * A request is aborted once no caller waits for any of its blocks.
 *
 * @param {Object}   options
 * @param {Function} options.fetch     apiFetch.
 * @param {string}   options.path      Path of the render route.
 * @param {number}   options.maxBlocks Maximum blocks per request.
 * @return {Object} Renderer with peek() and render().
 */
export function createRenderer( { fetch, path, maxBlocks } ) {
	const cache = new Map();
	const pending = new Map();
	let queue = [];
	let scheduled = false;

	function schedule() {
		if ( ! scheduled ) {
			scheduled = true;
			setTimeout( flush, 0 );
		}
	}

	function flush() {
		scheduled = false;
		const jobs = queue;
		queue = [];
		for ( let start = 0; start < jobs.length; start += maxBlocks ) {
			send( jobs.slice( start, start + maxBlocks ) );
		}
	}

	function send( jobs ) {
		const controller = new AbortController();
		// Prefetched blocks have no caller yet; they never keep a batch from being aborted.
		const batch = {
			controller,
			waiting: jobs.filter( ( job ) => job.waiters > 0 ).length,
		};
		for ( const job of jobs ) {
			job.batch = batch;
		}
		fetch( {
			path,
			method: 'POST',
			data: { blocks: jobs.map( ( job ) => job.entry ) },
			signal: controller.signal,
		} ).then(
			( response ) => {
				jobs.forEach( ( job, index ) => {
					pending.delete( job.key );
					const result = response?.results?.[ index ];
					if ( result && typeof result.html === 'string' ) {
						cache.set( job.key, result.html );
						job.resolve( result.html );
					} else {
						job.reject(
							Object.assign(
								new Error(
									result?.error?.message ??
										'The render route returned no result.'
								),
								{ code: result?.error?.code }
							)
						);
					}
				} );
			},
			( error ) => {
				for ( const job of jobs ) {
					pending.delete( job.key );
					job.reject( error );
				}
			}
		);
	}

	return {
		/**
		 * @param {string} key
		 * @return {string|undefined} Cached markup.
		 */
		peek( key ) {
			return cache.get( key );
		},

		/**
		 * Renders a block in the next batch without waiting for it, so a block
		 * that mounts later (an inner block of a container) finds its markup.
		 *
		 * @param {string} key   Cache key of the entry.
		 * @param {Object} entry {name, attributes, postId?}.
		 */
		prefetch( key, entry ) {
			if ( cache.has( key ) || pending.has( key ) ) {
				return;
			}
			const job = { key, entry, waiters: 0 };
			job.promise = new Promise( ( resolve, reject ) => {
				job.resolve = resolve;
				job.reject = reject;
			} );
			// Nobody may wait for a prefetch; avoid unhandled rejections.
			job.promise.catch( () => {} );
			pending.set( key, job );
			queue.push( job );
			schedule();
		},

		/**
		 * @param {string} key   Cache key of the entry.
		 * @param {Object} entry {name, attributes, postId?}.
		 * @return {{promise: Promise<string>, cancel: Function}} Result.
		 */
		render( key, entry ) {
			if ( cache.has( key ) ) {
				return {
					promise: Promise.resolve( cache.get( key ) ),
					cancel: () => {},
				};
			}
			let job = pending.get( key );
			if ( ! job ) {
				job = { key, entry, waiters: 0 };
				job.promise = new Promise( ( resolve, reject ) => {
					job.resolve = resolve;
					job.reject = reject;
				} );
				pending.set( key, job );
				queue.push( job );
				schedule();
			}
			if ( job.waiters === 0 && job.batch ) {
				// A sent block nobody waited for any more is wanted again: keep its request alive.
				job.batch.waiting++;
			}
			job.waiters++;
			let cancelled = false;
			return {
				promise: job.promise,
				cancel() {
					if ( cancelled ) {
						return;
					}
					cancelled = true;
					job.waiters--;
					if ( job.waiters > 0 ) {
						return;
					}
					if ( ! job.batch ) {
						// Not sent yet: drop it from the queue.
						queue = queue.filter( ( queued ) => queued !== job );
						pending.delete( key );
						return;
					}
					job.batch.waiting--;
					if ( job.batch.waiting === 0 ) {
						job.batch.controller.abort();
					}
				},
			};
		},
	};
}

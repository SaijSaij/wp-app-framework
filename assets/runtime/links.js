/**
 * Values of link controls (`Link` in PHP), without React. A link is stored as
 * exactly four values: `url`, `opensInNewTab`, `kind` ("url" for a URL
 * entered freely, else "post", "term" or "attachment") and `id` (0 for a free
 * URL). The REST API rejects other keys in post meta, so nothing else from
 * LinkControl or a search result is kept.
 */

/** Kinds of LinkControl (search results of the block editor) by stored kind. */
const CONTROL_KINDS = {
	post: 'post-type',
	term: 'taxonomy',
	attachment: 'media',
};

/** Stored kinds by kind of LinkControl. */
const STORED_KINDS = {
	'post-type': 'post',
	taxonomy: 'term',
	media: 'attachment',
};

/** Results per search of the form outside the block editor. */
export const PER_PAGE = 20;

/**
 * @typedef {Object} Link
 * @property {string}                           url           The URL; of a linked object as shown when it was chosen.
 * @property {boolean}                          opensInNewTab Open the link in a new tab.
 * @property {'url'|'post'|'term'|'attachment'} kind          A free URL or the kind of the linked object.
 * @property {number}                           id            ID of the linked object; 0 for a free URL.
 */

/**
 * @return {Link} A link without URL.
 */
export function emptyLink() {
	return { url: '', opensInNewTab: false, kind: 'url', id: 0 };
}

/**
 * Whether a link points to a post, term or media file (not a free URL).
 *
 * @param {?Link} link
 * @return {boolean} Linked.
 */
export function isLinked( link ) {
	return !! link && link.kind !== 'url' && link.id > 0;
}

/**
 * The value of LinkControl for a stored link: with ID and kind if it is linked,
 * so LinkControl shows it as synced (`handleEntities`).
 *
 * @param {?Link} link
 * @return {Object} Value for LinkControl.
 */
export function toLinkControlValue( link ) {
	const value = {
		url: link?.url ?? '',
		opensInNewTab: link?.opensInNewTab === true,
	};
	if ( isLinked( link ) ) {
		value.id = link.id;
		value.kind = CONTROL_KINDS[ link.kind ];
	}
	return value;
}

/**
 * The stored link for a value of LinkControl: a search result with ID becomes
 * a linked post, term or media file, everything else a free URL.
 *
 * @param {?Object} value Value from LinkControl's onChange.
 * @return {Link} Stored link.
 */
export function fromLinkControlValue( value ) {
	const url = typeof value?.url === 'string' ? value.url : '';
	const opensInNewTab = value?.opensInNewTab === true;
	const id = Number( value?.id );
	const kind = STORED_KINDS[ value?.kind ];
	if ( kind !== undefined && Number.isInteger( id ) && id > 0 ) {
		return { url, opensInNewTab, kind, id };
	}
	return { url, opensInNewTab, kind: 'url', id: 0 };
}

/**
 * The stored link for a post chosen in the search of the form.
 *
 * @param {Object}  result        Result of /wp/v2/search (`id`, `url`).
 * @param {boolean} opensInNewTab
 * @return {Link} Linked post.
 */
export function linkFromSearchResult( result, opensInNewTab ) {
	return { url: result.url, opensInNewTab, kind: 'post', id: result.id };
}

/**
 * A free URL with the address of a linked object: "Unlink" keeps it. The
 * stored URL of a linked object is only what was shown when it was chosen, so
 * the current URL of the object is passed when it is known.
 *
 * @param {Link}   link
 * @param {string} [url] Current URL of the linked object.
 * @return {Link} Free URL.
 */
export function unlink( link, url ) {
	return {
		url: url || link.url,
		opensInNewTab: link.opensInNewTab,
		kind: 'url',
		id: 0,
	};
}

/**
 * The form searches posts only (published posts of public post types, like
 * the block editor's search).
 *
 * @param {string} text Search text.
 * @return {string} REST path of a search.
 */
export function searchPath( text ) {
	const params = new URLSearchParams( {
		type: 'post',
		subtype: 'any',
		search: text,
		per_page: String( PER_PAGE ),
		_fields: 'id,title,url',
	} );
	return `/wp/v2/search?${ params.toString() }`;
}

/**
 * The request for the title and the current URL of a linked object; media
 * files are not part of /wp/v2/search.
 *
 * @param {Link} link Linked link.
 * @return {string} REST path that returns a list with the object.
 */
export function includePath( link ) {
	if ( link.kind === 'attachment' ) {
		return `/wp/v2/media?${ new URLSearchParams( {
			include: String( link.id ),
			_fields: 'id,title,source_url',
		} ).toString() }`;
	}
	return `/wp/v2/search?${ new URLSearchParams( {
		type: link.kind,
		subtype: 'any',
		include: String( link.id ),
		_fields: 'id,title,url',
	} ).toString() }`;
}

/**
 * The URL of an object from a result: /wp/v2/search returns `url`, media
 * files `source_url`.
 *
 * @param {Object} result
 * @return {string} URL, empty if there is none.
 */
export function resultUrl( result ) {
	return result?.url ?? result?.source_url ?? '';
}

/**
 * The title of an object from a result: /wp/v2/search returns it as string,
 * collections as `{ rendered }`.
 *
 * @param {Object} result
 * @return {string} Title, empty if there is none.
 */
export function resultTitle( result ) {
	return result?.title?.rendered ?? result?.title ?? '';
}

/**
 * A short form of a URL for the compact display: without protocol and
 * "www.", without trailing slash, shortened in the middle.
 *
 * @param {string} url
 * @param {number} [length] Maximum length.
 * @return {string} Display text.
 */
export function displayUrl( url, length = 32 ) {
	const text = url
		.replace( /^[a-z][a-z0-9+.-]*:\/\/(www\.)?/i, '' )
		.replace( /\/$/, '' );
	if ( text.length <= length ) {
		return text;
	}
	const half = Math.floor( ( length - 1 ) / 2 );
	return `${ text.slice( 0, half ) }…${ text.slice( text.length - half ) }`;
}

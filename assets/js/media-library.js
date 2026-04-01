/**
 * WP Folder Boss — Media Library Grid Integration
 */
/* global wpfbData */

( function () {
	'use strict';

	var activeFolderId = -1;
	var injected = false;
	var syncPatched = false;
	var uploaderPatched = false;
	var MAX_POLL_ATTEMPTS = 300;
	var POLL_INTERVAL_MS = 100;

	/* --------------------------------------------------------- */
	/*  1. Show grid sidebar with fixed positioning via CSS      */
	/* --------------------------------------------------------- */

	function showGridSidebar() {
		if ( injected ) return true;

		var sidebar = document.getElementById( 'wpfb-grid-sidebar' );
		if ( ! sidebar ) return false;

		var mediaFrame = document.querySelector( '.media-frame' );
		if ( ! mediaFrame ) return false;

		// Show the sidebar
		sidebar.style.display = '';
		document.body.classList.add( 'wpfb-has-grid-sidebar' );
		injected = true;

		// Initialize the folder tree on this sidebar
		if ( typeof window.WPFBTreeInit === 'function' ) {
			window.WPFBTreeInit( sidebar );
		}

		return true;
	}

	/* --------------------------------------------------------- */
	/*  2. Patch wp.media.model.Query.prototype.sync             */
	/* --------------------------------------------------------- */

	function patchSync() {
		if ( syncPatched ) return true;
		if ( typeof wp === 'undefined' || ! wp.media || ! wp.media.model || ! wp.media.model.Query ) {
			return false;
		}

		var OrigSync = wp.media.model.Query.prototype.sync;

		wp.media.model.Query.prototype.sync = function ( method, model, options ) {
			if ( 'read' === method && activeFolderId !== -1 ) {
				options = options || {};
				options.data = options.data || {};

				if ( typeof options.data.query === 'object' && options.data.query !== null ) {
					options.data.query.wpfb_folder = activeFolderId;
				} else {
					options.data.wpfb_folder = activeFolderId;
				}
			}
			return OrigSync.call( this, method, model, options );
		};

		syncPatched = true;
		return true;
	}

	/* --------------------------------------------------------- */
	/*  3. Patch wp.Uploader for auto-assign on upload           */
	/* --------------------------------------------------------- */

	function patchUploader() {
		if ( uploaderPatched ) return true;
		if ( typeof wp === 'undefined' || ! wp.Uploader ) {
			return false;
		}

		var origInit = wp.Uploader.prototype.init;

		wp.Uploader.prototype.init = function () {
			if ( origInit ) {
				origInit.apply( this, arguments );
			}

			this.uploader.bind( 'FileUploaded', function ( up, file, response ) {
				if ( activeFolderId <= 0 ) return;

				var parsed;
				try {
					parsed = JSON.parse( response.response );
				} catch ( e ) {
					return;
				}

				var attachmentId = parsed && parsed.id;
				if ( ! attachmentId ) return;

				fetch( wpfbData.restUrl + '/assign', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': wpfbData.nonce,
					},
					body: JSON.stringify( {
						folder_id: activeFolderId,
						item_ids: [ attachmentId ],
						item_type: 'post',
					} ),
				} ).catch( function () {} );
			} );
		};

		uploaderPatched = true;
		return true;
	}

	/* --------------------------------------------------------- */
	/*  4. Poll until sidebar and media frame are ready          */
	/* --------------------------------------------------------- */

	function poll() {
		var sidebarDone = showGridSidebar();
		var syncDone = patchSync();
		var uploaderDone = patchUploader();

		if ( sidebarDone && syncDone && uploaderDone ) return;

		var attempts = 0;
		var iv = setInterval( function () {
			attempts++;
			sidebarDone = sidebarDone || showGridSidebar();
			syncDone = syncDone || patchSync();
			uploaderDone = uploaderDone || patchUploader();

			if ( ( sidebarDone && syncDone && uploaderDone ) || attempts > MAX_POLL_ATTEMPTS ) {
				clearInterval( iv );
			}
		}, POLL_INTERVAL_MS );
	}

	/* --------------------------------------------------------- */
	/*  5. Listen for folder selection and refresh grid          */
	/* --------------------------------------------------------- */

	document.addEventListener( 'wpfb:folder-selected', function ( e ) {
		if ( e.detail.context !== 'media' ) return;
		activeFolderId = parseInt( e.detail.folderId, 10 );

		// Grid mode: refresh via wp.media
		if ( typeof wp !== 'undefined' && wp.media && wp.media.frame ) {
			try {
				var content = wp.media.frame.content.get();
				if ( content && content.collection ) {
					if ( activeFolderId === -1 ) {
						content.collection.props.unset( 'wpfb_folder' );
					} else {
						content.collection.props.set( 'wpfb_folder', activeFolderId );
					}
					content.collection.reset();
					content.collection.more();
				}
			} catch ( err ) {}
		}
	} );

	/* --------------------------------------------------------- */
	/*  6. Refresh grid when items are moved                     */
	/* --------------------------------------------------------- */

	document.addEventListener( 'wpfb:items-moved', function () {
		if ( typeof wp !== 'undefined' && wp.media && wp.media.frame ) {
			try {
				var content = wp.media.frame.content.get();
				if ( content && content.collection ) {
					content.collection.reset();
					content.collection.more();
				}
			} catch ( e ) {}
		}
	} );

	/* --------------------------------------------------------- */
	/*  7. Start polling                                         */
	/* --------------------------------------------------------- */

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', poll );
	} else {
		poll();
	}

} )();
